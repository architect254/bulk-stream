<?php

namespace Tests\Feature;

use App\Application\Dashboard\OperationalAnalyticsReadModel;
use App\Domain\Cargo\Support\DecimalMath;
use App\Enums\CommodityCode;
use App\Enums\DischargeStatus;
use App\Enums\TruckDispatchStatus;
use App\Enums\VesselStatus;
use App\Models\Commodity;
use App\Models\Discharge;
use App\Models\DischargeLog;
use App\Models\InventoryMovement;
use App\Models\Tank;
use App\Models\TruckDispatch;
use App\Models\Vessel;
use App\Support\OperationalTime;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalAnalyticsReadModelTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_period_summary_agrees_with_completed_operations(): void
    {
        $this->freezeAt('2026-11-04 10:00:00');

        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 5000, 'current_quantity' => 0]);
        $vessel = Vessel::factory()->create(['status' => VesselStatus::Arriving]);

        $discharge = Discharge::factory()->create([
            'vessel_id' => $vessel->id,
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => DischargeStatus::Draft,
        ]);
        DischargeLog::factory()->create([
            'discharge_id' => $discharge->id,
            'external_event_id' => 'analytics-inbound',
            'occurred_at' => now()->subMinute(),
            'gross_mass' => 1000,
        ]);
        app(\App\Actions\ProcessInboundDischarge::class)->execute($discharge->fresh(['logs', 'tank', 'commodity', 'vessel']));

        $dispatch = TruckDispatch::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'quantity' => 100,
            'status' => TruckDispatchStatus::Planned,
            'truck_registration' => 'KAA 123A',
            'driver_name' => 'Driver',
        ]);
        app(\App\Actions\ProcessTruckDispatch::class)->execute($dispatch->fresh(['tank', 'commodity']));

        $readModel = app(OperationalAnalyticsReadModel::class);
        [$start, $end] = $readModel->resolvePeriod('today');

        $summary = $readModel->periodSummary($start, $end);

        $this->assertSame(1, $summary['received']['operations']);
        $this->assertSame('1000.000', $summary['received']['gross_mass']);
        $this->assertSame('5.000', $summary['received']['transit_loss_mass']);
        $this->assertSame('995.000', $summary['received']['net_received_mass']);
        $this->assertSame(1, $summary['dispatched']['operations']);
        $this->assertSame('100.000', $summary['dispatched']['quantity']);
    }

    public function test_commodity_breakdown_does_not_double_count(): void
    {
        $this->freezeAt('2026-11-04 10:00:00');

        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 5000, 'current_quantity' => 0]);
        $vessel = Vessel::factory()->create(['status' => VesselStatus::Arriving]);

        $discharge = Discharge::factory()->create([
            'vessel_id' => $vessel->id,
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => DischargeStatus::Draft,
        ]);
        DischargeLog::factory()->create([
            'discharge_id' => $discharge->id,
            'external_event_id' => 'cb-1',
            'occurred_at' => now()->subMinute(),
            'gross_mass' => 1000,
        ]);
        DischargeLog::factory()->create([
            'discharge_id' => $discharge->id,
            'external_event_id' => 'cb-2',
            'occurred_at' => now()->subSeconds(30),
            'gross_mass' => 1000,
        ]);
        app(\App\Actions\ProcessInboundDischarge::class)->execute($discharge->fresh(['logs', 'tank', 'commodity', 'vessel']));

        $readModel = app(OperationalAnalyticsReadModel::class);
        [$start, $end] = $readModel->resolvePeriod('today');

        $row = $readModel->commoditySummary($start, $end)->firstWhere('code', CommodityCode::CPO->value);

        $this->assertSame(1, $row['received_operations']);
        $this->assertSame('2000.000', $row['received_gross_mass']);
        $this->assertSame('10.000', $row['transit_loss_mass']);
        $this->assertSame('1990.000', $row['received_net_mass']);
    }

    public function test_tank_utilization_handles_zero_capacity(): void
    {
        $tank = Tank::factory()->create(['capacity' => 0, 'current_quantity' => 0, 'code' => 'T-ZERO']);

        $row = app(OperationalAnalyticsReadModel::class)->tankInventoryAnalysis()->firstWhere('code', 'T-ZERO');

        $this->assertSame('0.00', $row['utilization']);
    }

    public function test_reconciliation_reports_matching_and_empty_histories(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 1000, 'current_quantity' => 0]);

        $rows = app(OperationalAnalyticsReadModel::class)->reconciliation();
        $row = $rows->firstWhere('code', $tank->code);

        $this->assertSame('0.000', $row['stored_balance']);
        $this->assertSame('0.000', $row['reconstructed_balance']);
        $this->assertSame('0.000', $row['difference']);
        $this->assertSame('Reconciled', $row['status']);
        $this->assertSame(0, $row['movement_count']);

        InventoryMovement::factory()->create([
            'tank_id' => $tank->id,
            'commodity_id' => $commodity->id,
            'movement_type' => \App\Enums\InventoryMovementType::Inbound,
            'quantity' => 100,
            'balance_before' => 0,
            'balance_after' => 100,
            'occurred_at' => now(),
        ]);
        $tank->forceFill(['current_quantity' => 100])->save();

        $row = app(OperationalAnalyticsReadModel::class)->reconciliation()->firstWhere('code', $tank->code);

        $this->assertSame('100.000', $row['stored_balance']);
        $this->assertSame('100.000', $row['reconstructed_balance']);
        $this->assertSame('Reconciled', $row['status']);
    }

    public function test_reconciliation_reports_non_zero_difference_without_mutation(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 1000]);
        $tank->forceFill(['current_quantity' => 125])->save();

        InventoryMovement::factory()->create([
            'tank_id' => $tank->id,
            'commodity_id' => $commodity->id,
            'movement_type' => \App\Enums\InventoryMovementType::Inbound,
            'quantity' => 100,
            'balance_before' => 0,
            'balance_after' => 100,
            'occurred_at' => now(),
        ]);

        $row = app(OperationalAnalyticsReadModel::class)->reconciliation()->firstWhere('code', $tank->code);

        $this->assertSame('125.000', $row['stored_balance']);
        $this->assertSame('100.000', $row['reconstructed_balance']);
        $this->assertSame('25.000', $row['difference']);
        $this->assertSame('Discrepancy', $row['status']);
        $this->assertSame('125.000', (string) $tank->fresh()->current_quantity);
    }

    public function test_reporting_period_respects_nairobi_boundaries(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-04 22:30:00', 'UTC'));

        [$start, $end, $label] = app(OperationalAnalyticsReadModel::class)->resolvePeriod('today');

        $this->assertSame('2026-11-04 21:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-05 20:59:59', $end->format('Y-m-d H:i:s'));
        $this->assertStringContainsString('5 Nov 2026', $label);

        CarbonImmutable::setTestNow();
    }

    public function test_cancelled_and_incomplete_operations_are_excluded(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 1000, 'current_quantity' => 0]);

        Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => DischargeStatus::Draft,
            'gross_mass' => 500,
            'transit_loss_mass' => 2.5,
            'net_received_mass' => 497.5,
            'completed_at' => now(),
        ]);
        TruckDispatch::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'quantity' => 50,
            'status' => TruckDispatchStatus::Cancelled,
            'dispatched_at' => now(),
        ]);

        $readModel = app(OperationalAnalyticsReadModel::class);
        [$start, $end] = $readModel->resolvePeriod('today');

        $summary = $readModel->periodSummary($start, $end);

        $this->assertSame(0, $summary['received']['operations']);
        $this->assertSame('0.000', $summary['received']['gross_mass']);
        $this->assertSame(0, $summary['dispatched']['operations']);
        $this->assertSame('0.000', $summary['dispatched']['quantity']);
    }


    public function test_empty_nonzero_history_is_unverifiable(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 1000]);
        $tank->forceFill(['current_quantity' => 25])->save();

        $row = app(OperationalAnalyticsReadModel::class)->reconciliation()->firstWhere('code', $tank->code);

        $this->assertSame('Unverifiable', $row['status']);
        $this->assertSame('25.000', $row['stored_balance']);
        $this->assertSame('0.000', $row['reconstructed_balance']);
        $this->assertSame(0, $row['movement_count']);
    }

    public function test_broken_balance_chain_is_unverifiable(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 1000]);

        InventoryMovement::factory()->create([
            'tank_id' => $tank->id,
            'commodity_id' => $commodity->id,
            'movement_type' => \App\Enums\InventoryMovementType::Inbound,
            'quantity' => 100,
            'balance_before' => 0,
            'balance_after' => 100,
            'occurred_at' => now(),
        ]);
        InventoryMovement::factory()->create([
            'tank_id' => $tank->id,
            'commodity_id' => $commodity->id,
            'movement_type' => \App\Enums\InventoryMovementType::Outbound,
            'quantity' => 10,
            'balance_before' => 999,
            'balance_after' => 989,
            'occurred_at' => now()->addMinute(),
        ]);

        $row = app(OperationalAnalyticsReadModel::class)->reconciliation()->firstWhere('code', $tank->code);

        $this->assertFalse($row['balance_chain_valid']);
        $this->assertSame('Unverifiable', $row['status']);
    }

    public function test_reconciliation_uses_a_bounded_number_of_queries(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 1000]);
        Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 1000]);

        \Illuminate\Support\Facades\DB::enableQueryLog();
        app(OperationalAnalyticsReadModel::class)->reconciliation();
        $queries = \Illuminate\Support\Facades\DB::getQueryLog();

        $movementQueries = array_filter($queries, fn (array $query): bool => str_contains($query['query'], 'inventory_movements'));

        $this->assertLessThanOrEqual(1, count($movementQueries));
    }

    public function test_previous_month_period_boundaries(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-15 10:00:00', OperationalTime::timezone()));

        [$start, $end, $label] = app(OperationalAnalyticsReadModel::class)->resolvePeriod('previous_month');

        $this->assertSame('2026-02-01 00:00:00', $start->setTimezone(OperationalTime::timezone())->format('Y-m-d H:i:s'));
        $this->assertSame('2026-02-28 23:59:59', $end->setTimezone(OperationalTime::timezone())->format('Y-m-d H:i:s'));
        $this->assertStringContainsString('Feb 2026', $label);

        CarbonImmutable::setTestNow();
    }

    private function freezeAt(string $datetime): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse($datetime, OperationalTime::timezone()));
    }
}
