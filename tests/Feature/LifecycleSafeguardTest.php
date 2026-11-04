<?php

namespace Tests\Feature;

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
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LifecycleSafeguardTest extends TestCase
{
    use RefreshDatabase;

    public function test_processed_discharge_edit_route_is_forbidden(): void
    {
        $user = User::factory()->create();
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 1000, 'current_quantity' => 0]);
        $vessel = Vessel::factory()->create(['status' => VesselStatus::Arriving]);
        $discharge = Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'vessel_id' => $vessel->id,
            'status' => DischargeStatus::Draft,
        ]);
        DischargeLog::factory()->create([
            'discharge_id' => $discharge->id,
            'external_event_id' => 'evt-lifecycle',
            'occurred_at' => now()->subMinute(),
            'gross_mass' => 100,
        ]);

        app(\App\Actions\ProcessInboundDischarge::class)->execute($discharge->fresh(['logs', 'tank', 'commodity', 'vessel']));

        $this->actingAs($user)
            ->get('/admin/discharges/' . $discharge->id . '/edit')
            ->assertForbidden();
    }

    public function test_dispatched_truck_dispatch_edit_route_is_forbidden(): void
    {
        $user = User::factory()->create();
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 1000, 'current_quantity' => 100]);
        $dispatch = TruckDispatch::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'quantity' => 10,
            'status' => TruckDispatchStatus::Planned,
        ]);

        app(\App\Actions\ProcessTruckDispatch::class)->execute($dispatch->fresh(['tank', 'commodity']));

        $this->actingAs($user)
            ->get('/admin/truck-dispatches/' . $dispatch->id . '/edit')
            ->assertForbidden();
    }

    public function test_cancelled_truck_dispatch_edit_route_is_forbidden(): void
    {
        $user = User::factory()->create();
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 1000, 'current_quantity' => 100]);
        $dispatch = TruckDispatch::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'quantity' => 10,
            'status' => TruckDispatchStatus::Cancelled,
        ]);

        $this->actingAs($user)
            ->get('/admin/truck-dispatches/' . $dispatch->id . '/edit')
            ->assertForbidden();

        $this->assertFalse(\App\Filament\Resources\TruckDispatches\TruckDispatchResource::canEdit($dispatch));
    }

    public function test_inventory_movement_edit_route_is_not_available(): void
    {
        $user = User::factory()->create();
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 1000, 'current_quantity' => 0]);
        $movement = InventoryMovement::factory()->create([
            'tank_id' => $tank->id,
            'commodity_id' => $commodity->id,
            'movement_type' => \App\Enums\InventoryMovementType::Inbound,
            'quantity' => 10,
            'balance_before' => 0,
            'balance_after' => 10,
            'occurred_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/admin/inventory-movements/' . $movement->id . '/edit')
            ->assertNotFound();
    }
}
