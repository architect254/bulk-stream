<?php

namespace Tests\Feature;

use App\Actions\ProcessInboundDischarge;
use App\Actions\ProcessTruckDispatch;
use App\Application\Dashboard\TerminalDashboardReadModel;
use App\Support\OperationalTime;
use App\Domain\Cargo\Support\DecimalMath;
use App\Enums\CommodityCode;
use App\Enums\DischargeStatus;
use App\Enums\InventoryMovementType;
use App\Enums\TankStatus;
use App\Enums\TruckDispatchStatus;
use App\Enums\VesselStatus;
use App\Models\Commodity;
use App\Models\Discharge;
use App\Models\DischargeLog;
use App\Models\InventoryMovement;
use App\Models\Tank;
use App\Models\TruckDispatch;
use App\Models\Vessel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardReadModelTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_completed_inbound_discharge_contributes_net_mass_exactly_once(): void
    {
        $this->freezeAt('2026-11-04 10:00:00');

        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 5000,
            'current_quantity' => 0,
        ]);
        $vessel = Vessel::factory()->create(['status' => VesselStatus::Arriving]);

        $discharge = Discharge::factory()->create([
            'vessel_id' => $vessel->id,
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => DischargeStatus::Draft,
        ]);

        DischargeLog::factory()->create([
            'discharge_id' => $discharge->id,
            'external_event_id' => 'evt-early',
            'occurred_at' => now()->subMinutes(2),
            'gross_mass' => 400,
        ]);
        DischargeLog::factory()->create([
            'discharge_id' => $discharge->id,
            'external_event_id' => 'evt-late',
            'occurred_at' => now()->subMinute(),
            'gross_mass' => 600,
        ]);

        app(ProcessInboundDischarge::class)->execute($discharge->fresh());

        $dashboard = app(TerminalDashboardReadModel::class);
        $received = $dashboard->todayReceived();

        $this->assertSame(1, $received['operations']);
        $this->assertSame('1000.000', $received['gross_mass']);
        $this->assertSame('5.000', $received['transit_loss_mass']);
        $this->assertSame('995.000', $received['net_received_mass']);

        // Reading twice must not duplicate the aggregate.
        $this->assertSame($received, $dashboard->todayReceived());
        $this->assertSame(1, InventoryMovement::query()->where('movement_type', InventoryMovementType::Inbound)->count());
    }

    public function test_draft_discharges_are_excluded_from_received_totals(): void
    {
        $this->freezeAt('2026-11-04 10:00:00');

        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 5000,
        ]);
        $vessel = Vessel::factory()->create();

        Discharge::factory()->create([
            'vessel_id' => $vessel->id,
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => DischargeStatus::Draft,
            'gross_mass' => 1000,
            'transit_loss_mass' => 5,
            'net_received_mass' => 995,
            'completed_at' => null,
        ]);

        $received = app(TerminalDashboardReadModel::class)->todayReceived();

        $this->assertSame(0, $received['operations']);
        $this->assertSame('0.000', $received['gross_mass']);
        $this->assertSame('0.000', $received['transit_loss_mass']);
        $this->assertSame('0.000', $received['net_received_mass']);
    }

    public function test_completed_truck_dispatch_contributes_dispatched_quantity_exactly_once(): void
    {
        $this->freezeAt('2026-11-04 10:00:00');

        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 5000,
            'current_quantity' => 1000,
        ]);

        $dispatch = TruckDispatch::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'quantity' => 300,
            'status' => TruckDispatchStatus::Planned,
        ]);

        app(ProcessTruckDispatch::class)->execute($dispatch->fresh());

        $dashboard = app(TerminalDashboardReadModel::class);
        $dispatched = $dashboard->todayDispatched();

        $this->assertSame(1, $dispatched['operations']);
        $this->assertSame('300.000', $dispatched['quantity']);

        $this->assertSame($dispatched, $dashboard->todayDispatched());
        $this->assertSame(1, InventoryMovement::query()->where('movement_type', InventoryMovementType::Outbound)->count());
    }

    public function test_planned_dispatch_is_excluded_from_dispatched_totals(): void
    {
        $this->freezeAt('2026-11-04 10:00:00');

        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 5000,
        ]);

        TruckDispatch::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'quantity' => 300,
            'status' => TruckDispatchStatus::Planned,
            'dispatched_at' => null,
        ]);

        $dispatched = app(TerminalDashboardReadModel::class)->todayDispatched();

        $this->assertSame(0, $dispatched['operations']);
        $this->assertSame('0.000', $dispatched['quantity']);
    }

    public function test_inventory_aggregates_exclude_inactive_tanks_and_report_commodity_breakdown(): void
    {
        $cpo = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $cpol = Commodity::factory()->create(['code' => CommodityCode::CPOL]);
        $pfad = Commodity::factory()->create(['code' => CommodityCode::PFAD]);

        Tank::factory()->create([
            'commodity_id' => $cpo->id,
            'capacity' => 1000,
            'current_quantity' => 250,
            'status' => TankStatus::Active,
        ]);
        Tank::factory()->create([
            'commodity_id' => $cpo->id,
            'capacity' => 1000,
            'current_quantity' => 100,
            'status' => TankStatus::Active,
        ]);
        Tank::factory()->create([
            'commodity_id' => $cpol->id,
            'capacity' => 2000,
            'current_quantity' => 500,
            'status' => TankStatus::Active,
        ]);
        Tank::factory()->create([
            'commodity_id' => $pfad->id,
            'capacity' => 9000,
            'current_quantity' => 900,
            'status' => TankStatus::Inactive,
        ]);

        $inventory = app(TerminalDashboardReadModel::class)->inventory();

        $this->assertSame(3, $inventory['tanks']);
        $this->assertSame('850.000', $inventory['current_quantity']);
        $this->assertSame('4000.000', $inventory['capacity']);
        $this->assertSame('3150.000', $inventory['available_capacity']);

        $this->assertSame('350.000', $inventory['by_commodity'][CommodityCode::CPO->value]);
        $this->assertSame('500.000', $inventory['by_commodity'][CommodityCode::CPOL->value]);
        $this->assertSame('0.000', $inventory['by_commodity'][CommodityCode::PFAD->value]);
    }

    public function test_today_boundaries_use_africa_nairobi_for_received_and_dispatched_mass(): void
    {
        $this->freezeAt('2026-11-04 12:00:00');

        $startOfToday = CarbonImmutable::parse('2026-11-03 21:00:00', 'UTC');
        $yesterday = CarbonImmutable::parse('2026-11-03 20:59:59', 'UTC');
        $endOfToday = CarbonImmutable::parse('2026-11-04 20:59:59', 'UTC');
        $tomorrow = CarbonImmutable::parse('2026-11-04 21:00:00', 'UTC');

        $inboundCommodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $inboundTank = Tank::factory()->create([
            'commodity_id' => $inboundCommodity->id,
            'capacity' => 10_000,
            'current_quantity' => 0,
        ]);

        $this->completedDischarge('50.000', $startOfToday, $inboundCommodity, $inboundTank);
        $this->completedDischarge('200.000', $yesterday, $inboundCommodity, $inboundTank);
        $this->completedDischarge('300.000', $endOfToday, $inboundCommodity, $inboundTank);
        $this->completedDischarge('400.000', $tomorrow, $inboundCommodity, $inboundTank);

        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPOL]);
        $tank = Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 5000,
            'current_quantity' => 1000,
        ]);

        $this->dispatchedTruck($commodity, $tank, '10.000', $startOfToday);
        $this->dispatchedTruck($commodity, $tank, '20.000', $yesterday);
        $this->dispatchedTruck($commodity, $tank, '30.000', $endOfToday);
        $this->dispatchedTruck($commodity, $tank, '40.000', $tomorrow);

        $dashboard = app(TerminalDashboardReadModel::class);

        $received = $dashboard->todayReceived();
        $dispatched = $dashboard->todayDispatched();

        $this->assertSame(2, $received['operations']);
        $this->assertSame('350.000', $received['net_received_mass']);
        $this->assertSame(2, $dispatched['operations']);
        $this->assertSame('40.000', $dispatched['quantity']);
    }

    public function test_recent_movements_are_ordered_newest_first(): void
    {
        $this->freezeAt('2026-11-04 10:00:00');

        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 5000,
        ]);

        $oldest = InventoryMovement::factory()->create([
            'tank_id' => $tank->id,
            'commodity_id' => $commodity->id,
            'occurred_at' => now()->subMinutes(3),
        ]);
        $middle = InventoryMovement::factory()->create([
            'tank_id' => $tank->id,
            'commodity_id' => $commodity->id,
            'occurred_at' => now()->subMinutes(2),
        ]);
        $newest = InventoryMovement::factory()->create([
            'tank_id' => $tank->id,
            'commodity_id' => $commodity->id,
            'occurred_at' => now()->subMinute(),
        ]);

        $movements = app(TerminalDashboardReadModel::class)->recentMovements(3);

        $this->assertSame(
            [$newest->id, $middle->id, $oldest->id],
            $movements->pluck('id')->all(),
        );
    }

    public function test_attention_groups_include_pending_and_inconsistent_records(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 5000,
            'current_quantity' => 500,
        ]);
        $vessel = Vessel::factory()->create(['status' => VesselStatus::Arriving]);

        Discharge::factory()->create([
            'vessel_id' => $vessel->id,
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => DischargeStatus::Draft,
        ]);
        Discharge::factory()->create([
            'vessel_id' => $vessel->id,
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => DischargeStatus::Processing,
        ]);
        Discharge::factory()->create([
            'vessel_id' => $vessel->id,
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => DischargeStatus::Failed,
        ]);
        TruckDispatch::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => TruckDispatchStatus::Planned,
        ]);
        TruckDispatch::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => TruckDispatchStatus::Loading,
        ]);
        Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 1000,
            'current_quantity' => 100,
            'status' => TankStatus::Inactive,
        ]);

        $dashboard = app(TerminalDashboardReadModel::class);
        $groups = collect($dashboard->attentionGroups())->keyBy('key');

        $this->assertSame(1, $groups['draft-discharges']['count']);
        $this->assertSame(1, $groups['processing-discharges']['count']);
        $this->assertSame(1, $groups['failed-discharges']['count']);
        $this->assertSame(1, $groups['planned-dispatches']['count']);
        $this->assertSame(1, $groups['loading-dispatches']['count']);
        $this->assertSame(1, $groups['arriving-vessels']['count']);
        $this->assertSame(1, $groups['inactive-tanks-with-inventory']['count']);
        $this->assertSame(7, $dashboard->attentionCount());
        $this->assertArrayNotHasKey('tanks-over-capacity', $groups);
        $this->assertArrayNotHasKey('negative-tank-balances', $groups);
    }

    public function test_flow_counts_reflect_current_statuses(): void
    {
        $vessels = [];

        foreach (VesselStatus::cases() as $status) {
            $vessels[] = Vessel::factory()->create(['status' => $status]);
        }

        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 5000]);

        foreach (DischargeStatus::cases() as $status) {
            Discharge::factory()->create([
                'vessel_id' => $vessels[0]->id,
                'commodity_id' => $commodity->id,
                'tank_id' => $tank->id,
                'status' => $status,
            ]);
        }

        foreach (TruckDispatchStatus::cases() as $status) {
            TruckDispatch::factory()->create([
                'commodity_id' => $commodity->id,
                'tank_id' => $tank->id,
                'status' => $status,
            ]);
        }

        Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 1000,
            'status' => TankStatus::Inactive,
        ]);

        $flow = app(TerminalDashboardReadModel::class)->flow();

        $this->assertSame(1, $flow['vessels']['scheduled']);
        $this->assertSame(1, $flow['vessels']['arriving']);
        $this->assertSame(1, $flow['vessels']['discharged']);
        $this->assertSame(1, $flow['vessels']['departed']);
        $this->assertSame(4, $flow['vessels']['total']);

        $this->assertSame(1, $flow['discharges']['draft']);
        $this->assertSame(1, $flow['discharges']['processing']);
        $this->assertSame(1, $flow['discharges']['completed']);
        $this->assertSame(1, $flow['discharges']['failed']);

        $this->assertSame(1, $flow['tanks']['active']);
        $this->assertSame(1, $flow['tanks']['inactive']);

        $this->assertSame(1, $flow['dispatches']['planned']);
        $this->assertSame(1, $flow['dispatches']['loading']);
        $this->assertSame(1, $flow['dispatches']['dispatched']);
        $this->assertSame(1, $flow['dispatches']['cancelled']);
    }

    public function test_dashboard_queries_do_not_mutate_operational_records(): void
    {
        $this->freezeAt('2026-11-04 10:00:00');

        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 5000,
            'current_quantity' => 500,
        ]);
        $discharge = Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => DischargeStatus::Draft,
        ]);
        $dispatch = TruckDispatch::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => TruckDispatchStatus::Planned,
        ]);

        $before = [
            'tanks' => Tank::query()->count(),
            'discharges' => Discharge::query()->count(),
            'dispatches' => TruckDispatch::query()->count(),
            'movements' => InventoryMovement::query()->count(),
            'tank_quantity' => (string) $tank->fresh()->current_quantity,
            'discharge_status' => $discharge->fresh()->status,
            'dispatch_status' => $dispatch->fresh()->status,
        ];

        $dashboard = app(TerminalDashboardReadModel::class);
        $dashboard->todayReceived();
        $dashboard->todayDispatched();
        $dashboard->inventory();
        $dashboard->flow();
        $dashboard->attentionGroups();
        $dashboard->recentMovements();

        $after = [
            'tanks' => Tank::query()->count(),
            'discharges' => Discharge::query()->count(),
            'dispatches' => TruckDispatch::query()->count(),
            'movements' => InventoryMovement::query()->count(),
            'tank_quantity' => (string) $tank->fresh()->current_quantity,
            'discharge_status' => $discharge->fresh()->status,
            'dispatch_status' => $dispatch->fresh()->status,
        ];

        $this->assertSame($before, $after);
    }

    private function freezeAt(string $datetime): void
    {
        $moment = CarbonImmutable::parse($datetime, OperationalTime::timezone());

        Carbon::setTestNow($moment);
        CarbonImmutable::setTestNow($moment);
    }

    private function completedDischarge(
        string $netMass,
        CarbonImmutable $completedAt,
        ?Commodity $commodity = null,
        ?Tank $tank = null,
    ): Discharge {
        return Discharge::factory()->create([
            'commodity_id' => $commodity?->id,
            'tank_id' => $tank?->id,
            'status' => DischargeStatus::Completed,
            'gross_mass' => DecimalMath::add($netMass, '1.000'),
            'transit_loss_mass' => '1.000',
            'net_received_mass' => $netMass,
            'started_at' => $completedAt->subHour(),
            'completed_at' => $completedAt,
        ]);
    }

    private function dispatchedTruck(
        Commodity $commodity,
        Tank $tank,
        string $quantity,
        CarbonImmutable $dispatchedAt,
    ): TruckDispatch {
        return TruckDispatch::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'quantity' => $quantity,
            'status' => TruckDispatchStatus::Dispatched,
            'dispatched_at' => $dispatchedAt,
        ]);
    }
}
