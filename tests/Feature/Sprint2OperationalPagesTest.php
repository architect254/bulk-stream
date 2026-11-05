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
use Livewire\Livewire;
use Tests\TestCase;

class Sprint2OperationalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_vessel_discharge_create_form_excludes_discharged_vessels(): void
    {
        $eligible = Vessel::factory()->create(['name' => 'MV Eligible', 'status' => VesselStatus::Scheduled]);
        $ineligible = Vessel::factory()->create(['name' => 'MV Ineligible', 'status' => VesselStatus::Discharged]);

        Livewire::actingAs(User::factory()->create())
            ->test(\App\Filament\Resources\Discharges\Pages\CreateDischarge::class)
            ->assertSuccessful()
            ->assertSee($eligible->name)
            ->assertDontSee($ineligible->name);
    }

    public function test_operational_detail_pages_render_with_relations(): void
    {
        $user = User::factory()->create();
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 1000, 'current_quantity' => 100]);
        $vessel = Vessel::factory()->create(['status' => VesselStatus::Arriving]);
        $discharge = Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'vessel_id' => $vessel->id,
            'status' => DischargeStatus::Draft,
        ]);
        DischargeLog::factory()->create([
            'discharge_id' => $discharge->id,
            'external_event_id' => 'evt-s2',
            'occurred_at' => now()->subMinute(),
            'gross_mass' => 100,
        ]);
        $dispatch = TruckDispatch::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'quantity' => 10,
            'status' => TruckDispatchStatus::Planned,
            'truck_registration' => 'KAA 123A',
            'driver_name' => 'Driver',
        ]);
        $movement = InventoryMovement::factory()->create([
            'tank_id' => $tank->id,
            'commodity_id' => $commodity->id,
            'movement_type' => \App\Enums\InventoryMovementType::Inbound,
            'quantity' => 10,
            'balance_before' => 0,
            'balance_after' => 10,
            'occurred_at' => now(),
        ]);

        Livewire::actingAs($user)->test(\App\Filament\Resources\Vessels\Pages\ViewVessel::class, ['record' => $vessel->getKey()])->assertSuccessful();
        Livewire::actingAs($user)->test(\App\Filament\Resources\Tanks\Pages\ViewTank::class, ['record' => $tank->getKey()])->assertSuccessful();
        Livewire::actingAs($user)->test(\App\Filament\Resources\Discharges\Pages\ViewDischarge::class, ['record' => $discharge->getKey()])->assertSuccessful();
        Livewire::actingAs($user)->test(\App\Filament\Resources\TruckDispatches\Pages\ViewTruckDispatch::class, ['record' => $dispatch->getKey()])->assertSuccessful();
        Livewire::actingAs($user)->test(\App\Filament\Resources\InventoryMovements\Pages\ViewInventoryMovement::class, ['record' => $movement->getKey()])->assertSuccessful();
    }
}
