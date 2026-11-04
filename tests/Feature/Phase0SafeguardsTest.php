<?php

namespace Tests\Feature;

use App\Actions\ProcessInboundDischarge;
use App\Actions\ProcessTruckDispatch;
use App\Domain\Cargo\Exceptions\CommodityMismatch;
use App\Domain\Cargo\Exceptions\InvalidCargoState;
use App\Enums\CommodityCode;
use App\Enums\DischargeStatus;
use App\Enums\TankStatus;
use App\Enums\TruckDispatchStatus;
use App\Enums\VesselStatus;
use App\Models\Commodity;
use App\Models\Discharge;
use App\Models\DischargeLog;
use App\Models\InventoryMovement;
use App\Models\Tank;
use App\Models\TruckDispatch;
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class Phase0SafeguardsTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_t101_is_cpo_and_active(): void
    {
        $this->seed();

        $tank = Tank::where('code', 'T-101')->firstOrFail();

        $this->assertSame(CommodityCode::CPO, $tank->commodity->code);
        $this->assertSame(TankStatus::Active, $tank->status);
    }

    public function test_seed_is_idempotent(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(3, \App\Models\Commodity::count());
        $this->assertSame(4, Tank::count());
        $this->assertSame(3, Vessel::count());
    }

    public function test_inactive_tank_is_rejected_for_inbound_processing(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 1000,
            'current_quantity' => 0,
            'status' => TankStatus::Inactive,
        ]);
        $vessel = Vessel::factory()->create(['status' => VesselStatus::Arriving]);
        $discharge = Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'vessel_id' => $vessel->id,
            'status' => DischargeStatus::Draft,
        ]);
        DischargeLog::factory()->create([
            'discharge_id' => $discharge->id,
            'external_event_id' => 'evt-inactive-inbound',
            'occurred_at' => now()->subMinute(),
            'gross_mass' => 100,
        ]);

        $this->expectException(InvalidCargoState::class);

        app(ProcessInboundDischarge::class)->execute($discharge->fresh(['logs', 'tank', 'commodity', 'vessel']));
    }

    public function test_inactive_tank_is_rejected_for_outbound_processing(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 1000,
            'current_quantity' => 100,
            'status' => TankStatus::Inactive,
        ]);
        $dispatch = TruckDispatch::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'quantity' => 10,
            'status' => TruckDispatchStatus::Planned,
        ]);

        $this->expectException(InvalidCargoState::class);

        app(ProcessTruckDispatch::class)->execute($dispatch->fresh(['tank', 'commodity']));
    }

    public function test_available_capacity_excludes_inactive_tanks(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 1000,
            'current_quantity' => 0,
            'status' => TankStatus::Active,
        ]);
        Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 500,
            'current_quantity' => 0,
            'status' => TankStatus::Inactive,
        ]);

        $activeCapacity = (string) Tank::active()->sum('capacity');

        $this->assertSame('1000', $activeCapacity);
    }

    public function test_vessel_status_is_not_mass_assignable(): void
    {
        $vessel = Vessel::create([
            'name' => 'MV Test',
            'status' => VesselStatus::Departed,
        ]);

        $this->assertSame(VesselStatus::Scheduled, $vessel->status);
    }

    public function test_tank_capacity_cannot_be_reduced_below_current_inventory(): void
    {
        $tank = Tank::factory()->create([
            'capacity' => 1000,
            'current_quantity' => 500,
        ]);

        $this->expectException(\App\Domain\Cargo\Exceptions\InvalidCargoQuantity::class);

        $tank->capacity = 400;
        $tank->save();
    }

    public function test_tank_commodity_cannot_be_changed_with_history(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $otherCommodity = Commodity::factory()->create(['code' => CommodityCode::CPOL]);
        $tank = Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 1000,
            'current_quantity' => 0,
        ]);
        $vessel = Vessel::factory()->create(['status' => VesselStatus::Arriving]);
        $discharge = Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'vessel_id' => $vessel->id,
            'status' => DischargeStatus::Draft,
        ]);

        $this->expectException(\App\Domain\Cargo\Exceptions\InvalidCargoState::class);

        $tank->commodity_id = $otherCommodity->id;
        $tank->save();
    }

    public function test_processed_discharge_cannot_be_edited_or_deleted(): void
    {
        $user = User::factory()->create();
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 1000,
            'current_quantity' => 0,
        ]);
        $vessel = Vessel::factory()->create(['status' => VesselStatus::Arriving]);
        $discharge = Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'vessel_id' => $vessel->id,
            'status' => DischargeStatus::Draft,
        ]);
        DischargeLog::factory()->create([
            'discharge_id' => $discharge->id,
            'external_event_id' => 'evt-phase0',
            'occurred_at' => now()->subMinute(),
            'gross_mass' => 100,
        ]);

        app(ProcessInboundDischarge::class)->execute($discharge->fresh(['logs', 'tank', 'commodity', 'vessel']));

        $completed = Discharge::find($discharge->id);

        $this->actingAs($user)
            ->get('/admin/discharges/' . $completed->id . '/edit')
            ->assertForbidden();

        $this->expectException(\App\Domain\Cargo\Exceptions\InvalidCargoState::class);
        // delete route may be absent; calling delete directly should be blocked
        $completed->delete();
    }

    public function test_dispatched_dispatch_cannot_be_processed_again(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 1000,
            'current_quantity' => 100,
        ]);
        $dispatch = TruckDispatch::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'quantity' => 10,
            'status' => TruckDispatchStatus::Planned,
        ]);

        app(ProcessTruckDispatch::class)->execute($dispatch->fresh(['tank', 'commodity']));

        $dispatched = TruckDispatch::find($dispatch->id);
        app(ProcessTruckDispatch::class)->execute($dispatched->fresh(['tank', 'commodity']));

        $this->assertSame(TruckDispatchStatus::Dispatched, $dispatched->fresh()->status);
        $this->assertSame('90.000', (string) Tank::find($tank->id)->current_quantity);
        $this->assertSame(1, InventoryMovement::count());
    }

    public function test_tank_balance_is_not_mass_assignable(): void
    {
        $tank = Tank::factory()->create(['current_quantity' => 50]);

        $tank->fill(['current_quantity' => 999]);
        $tank->save();

        $this->assertSame('50.000', (string) $tank->fresh()->current_quantity);
    }

    public function test_inventory_movements_are_read_only_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/inventory-movements/create')
            ->assertNotFound();
    }

    public function test_generated_reference_and_idempotency_key_are_stable(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 1000,
            'current_quantity' => 0,
        ]);
        $vessel = Vessel::factory()->create(['status' => VesselStatus::Arriving]);
        $discharge = Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'vessel_id' => $vessel->id,
            'status' => DischargeStatus::Draft,
        ]);
        DischargeLog::factory()->create([
            'discharge_id' => $discharge->id,
            'external_event_id' => 'evt-stable',
            'occurred_at' => now()->subMinute(),
            'gross_mass' => 100,
        ]);

        $first = app(ProcessInboundDischarge::class)->execute($discharge->fresh(['logs', 'tank', 'commodity', 'vessel']));
        $second = app(ProcessInboundDischarge::class)->execute($first->fresh(['logs', 'tank', 'commodity', 'vessel']));

        $this->assertSame($first->reference, $second->reference);
        $this->assertSame($first->idempotency_key, $second->idempotency_key);
        $this->assertNotNull($first->reference);
    }
}
