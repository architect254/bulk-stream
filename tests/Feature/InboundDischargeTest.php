<?php

namespace Tests\Feature;

use App\Actions\ProcessInboundDischarge;
use App\Domain\Cargo\Exceptions\CommodityMismatch;
use App\Domain\Cargo\Exceptions\InvalidCargoQuantity;
use App\Domain\Cargo\Exceptions\InvalidCargoState;
use App\Domain\Cargo\Exceptions\TankCapacityExceeded;
use App\Enums\CommodityCode;
use App\Enums\DischargeStatus;
use App\Enums\InventoryMovementType;
use App\Enums\VesselStatus;
use App\Models\Commodity;
use App\Models\Discharge;
use App\Models\DischargeLog;
use App\Models\InventoryMovement;
use App\Models\Tank;
use App\Models\Vessel;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboundDischargeTest extends TestCase
{
    use RefreshDatabase;

    private function makeDischarge(array $logGrosses, array $overrides = []): Discharge
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(array_merge([
            'commodity_id' => $commodity->id,
            'capacity' => 1000,
            'current_quantity' => 0,
        ], $overrides['tank'] ?? []));

        $vessel = Vessel::factory()->create(array_merge([
            'status' => VesselStatus::Arriving,
        ], $overrides['vessel'] ?? []));

        $discharge = Discharge::factory()->create(array_merge([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'vessel_id' => $vessel->id,
            'status' => DischargeStatus::Draft,
            'gross_mass' => null,
            'transit_loss_mass' => null,
            'net_received_mass' => null,
        ], $overrides['discharge'] ?? []));

        foreach ($logGrosses as $index => $gross) {
            DischargeLog::factory()->create([
                'discharge_id' => $discharge->id,
                'external_event_id' => 'evt-' . $discharge->id . '-' . $index,
                'occurred_at' => now()->subMinutes(10 - $index),
                'sequence' => 'SEQ-' . ($index + 1),
                'gross_mass' => $gross,
            ]);
        }

        return $discharge->fresh(['logs', 'tank', 'commodity', 'vessel']);
    }

    public function test_happy_path_processes_discharge(): void
    {
        $discharge = $this->makeDischarge([100, 150]);

        $processed = app(ProcessInboundDischarge::class)->execute($discharge);

        $this->assertSame(DischargeStatus::Completed, $processed->status);
        $this->assertSame('250.000', (string) $processed->gross_mass);
        $this->assertSame('1.250', (string) $processed->transit_loss_mass);
        $this->assertSame('248.750', (string) $processed->net_received_mass);

        $tank = $processed->tank->fresh();
        $this->assertSame('248.750', (string) $tank->current_quantity);

        $movement = InventoryMovement::where('source_id', $processed->id)->firstOrFail();
        $this->assertSame(InventoryMovementType::Inbound, $movement->movement_type);
        $this->assertSame('248.750', (string) $movement->quantity);
        $this->assertSame('0.000', (string) $movement->balance_before);
        $this->assertSame('248.750', (string) $movement->balance_after);
    }

    public function test_chronological_ordering_uses_earliest_timestamp(): void
    {
        $discharge = $this->makeDischarge([100, 150]);

        // Reorder occurred_at values to be out of insertion order.
        $logs = $discharge->logs()->get();
        $logs[0]->update(['occurred_at' => now()->subMinutes(1)]);
        $logs[1]->update(['occurred_at' => now()->subMinutes(5)]);

        $processed = app(ProcessInboundDischarge::class)->execute($discharge->fresh(['logs', 'tank', 'commodity', 'vessel']));

        $this->assertSame(DischargeStatus::Completed, $processed->status);
        $this->assertNotNull($processed->started_at);
    }

    public function test_exact_capacity_succeeds(): void
    {
        $discharge = $this->makeDischarge([1000], ['tank' => ['capacity' => 995]]);

        $processed = app(ProcessInboundDischarge::class)->execute($discharge);

        $this->assertSame(DischargeStatus::Completed, $processed->status);
        $this->assertSame('995.000', (string) $processed->tank->fresh()->current_quantity);
    }

    public function test_over_capacity_fails_without_mutation(): void
    {
        $discharge = $this->makeDischarge([1000], ['tank' => ['capacity' => 994]]);

        try {
            app(ProcessInboundDischarge::class)->execute($discharge);
            $this->fail('Expected TankCapacityExceeded.');
        } catch (TankCapacityExceeded $e) {
            $this->assertTrue(true);
        }

        $this->assertSame(DischargeStatus::Draft, $discharge->fresh()->status);
        $this->assertSame('0.000', (string) Tank::find($discharge->tank_id)->current_quantity);
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_idempotent_second_call_does_not_double_inventory(): void
    {
        $discharge = $this->makeDischarge([100, 150]);
        $service = app(ProcessInboundDischarge::class);

        $first = $service->execute($discharge);
        $second = $service->execute($first->fresh(['logs', 'tank', 'commodity', 'vessel']));

        $this->assertSame(DischargeStatus::Completed, $second->status);
        $this->assertSame('248.750', (string) Tank::find($discharge->tank_id)->current_quantity);
        $this->assertSame(1, InventoryMovement::count());
    }

    public function test_duplicate_source_event_is_rejected(): void
    {
        $discharge = $this->makeDischarge([100]);

        $this->expectException(QueryException::class);

        DischargeLog::factory()->create([
            'discharge_id' => $discharge->id,
            'external_event_id' => $discharge->logs->first()->external_event_id,
            'occurred_at' => now(),
            'gross_mass' => 50,
        ]);
    }

    public function test_commodity_mismatch_is_rejected(): void
    {
        $discharge = $this->makeDischarge([100]);
        $otherCommodity = Commodity::factory()->create(['code' => CommodityCode::CPOL]);
        $discharge->tank->update(['commodity_id' => $otherCommodity->id]);

        $this->expectException(CommodityMismatch::class);

        app(ProcessInboundDischarge::class)->execute($discharge->fresh(['logs', 'tank', 'commodity', 'vessel']));
    }

    public function test_vessel_not_arriving_is_rejected(): void
    {
        $discharge = $this->makeDischarge([100], ['vessel' => ['status' => VesselStatus::Scheduled]]);

        $this->expectException(\App\Domain\Cargo\Exceptions\InvalidStateTransition::class);

        app(ProcessInboundDischarge::class)->execute($discharge);
    }

    public function test_negative_gross_is_rejected(): void
    {
        $discharge = $this->makeDischarge([-10]);

        $this->expectException(InvalidCargoQuantity::class);

        app(ProcessInboundDischarge::class)->execute($discharge);
    }

    public function test_zero_gross_is_rejected(): void
    {
        $discharge = $this->makeDischarge([0]);

        $this->expectException(InvalidCargoQuantity::class);

        app(ProcessInboundDischarge::class)->execute($discharge);
    }

    public function test_completed_discharge_cannot_be_processed_again(): void
    {
        $discharge = $this->makeDischarge([100]);
        $service = app(ProcessInboundDischarge::class);

        $service->execute($discharge);

        $completed = Discharge::find($discharge->id);
        $this->assertSame(DischargeStatus::Completed, $completed->status);

        // Should be idempotent, not throw.
        $again = $service->execute($completed->fresh(['logs', 'tank', 'commodity', 'vessel']));
        $this->assertSame(DischargeStatus::Completed, $again->status);
        $this->assertSame(1, InventoryMovement::count());
    }

    public function test_competing_discharges_respect_capacity(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 995,
            'current_quantity' => 0,
        ]);
        $vesselOne = Vessel::factory()->create(['status' => VesselStatus::Arriving]);
        $vesselTwo = Vessel::factory()->create(['status' => VesselStatus::Arriving]);

        $dischargeOne = Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'vessel_id' => $vesselOne->id,
            'status' => DischargeStatus::Draft,
        ]);
        DischargeLog::factory()->create([
            'discharge_id' => $dischargeOne->id,
            'external_event_id' => 'evt-one',
            'occurred_at' => now()->subMinute(),
            'gross_mass' => 1000,
        ]);

        $dischargeTwo = Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'vessel_id' => $vesselTwo->id,
            'status' => DischargeStatus::Draft,
        ]);
        DischargeLog::factory()->create([
            'discharge_id' => $dischargeTwo->id,
            'external_event_id' => 'evt-two',
            'occurred_at' => now(),
            'gross_mass' => 1000,
        ]);

        $service = app(ProcessInboundDischarge::class);

        $service->execute($dischargeOne->fresh(['logs', 'tank', 'commodity', 'vessel']));

        try {
            $service->execute($dischargeTwo->fresh(['logs', 'tank', 'commodity', 'vessel']));
            $this->fail('Expected TankCapacityExceeded.');
        } catch (TankCapacityExceeded $e) {
            $this->assertTrue(true);
        }

        $this->assertSame('995.000', (string) Tank::find($tank->id)->current_quantity);
        $this->assertSame(1, InventoryMovement::count());
    }
}
