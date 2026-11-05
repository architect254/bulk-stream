<?php

namespace Tests\Feature;

use App\Actions\ProcessInboundDischarge;
use App\Actions\ProcessTruckDispatch;
use App\Domain\Cargo\Exceptions\CommodityMismatch;
use App\Domain\Cargo\Exceptions\InsufficientTankInventory;
use App\Enums\CommodityCode;
use App\Enums\DischargeStatus;
use App\Enums\InventoryMovementType;
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

class Sprint2VerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_changing_commodity_resets_selected_tank(): void
    {
        $user = User::factory()->create();
        $cpo = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $cpol = Commodity::factory()->create(['code' => CommodityCode::CPOL]);
        $cpoTank = Tank::factory()->create(['commodity_id' => $cpo->id, 'code' => 'T-CPO']);
        $cpolTank = Tank::factory()->create(['commodity_id' => $cpol->id, 'code' => 'T-CPOL']);
        $vessel = Vessel::factory()->create(['status' => VesselStatus::Arriving]);

        Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Discharges\Pages\CreateDischarge::class)
            ->fillForm([
                'vessel_id' => $vessel->id,
                'commodity_id' => $cpo->id,
                'tank_id' => $cpoTank->id,
            ])
            ->assertFormSet(['commodity_id' => $cpo->id, 'tank_id' => $cpoTank->id])
            ->fillForm(['commodity_id' => $cpol->id])
            ->assertFormSet(['commodity_id' => $cpol->id, 'tank_id' => null]);
    }

    public function test_selecting_tank_derives_commodity(): void
    {
        $user = User::factory()->create();
        $cpol = Commodity::factory()->create(['code' => CommodityCode::CPOL]);
        $cpolTank = Tank::factory()->create(['commodity_id' => $cpol->id, 'code' => 'T-CPOL']);
        $vessel = Vessel::factory()->create(['status' => VesselStatus::Arriving]);

        Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Discharges\Pages\CreateDischarge::class)
            ->fillForm([
                'vessel_id' => $vessel->id,
                'tank_id' => $cpolTank->id,
            ])
            ->assertFormSet(['commodity_id' => $cpol->id, 'tank_id' => $cpolTank->id]);
    }

    public function test_tank_options_respect_compatibility(): void
    {
        $user = User::factory()->create();
        $cpo = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $cpol = Commodity::factory()->create(['code' => CommodityCode::CPOL]);
        $activeCpoTank = Tank::factory()->create(['commodity_id' => $cpo->id, 'code' => 'T-ACTIVE-CPO', 'status' => \App\Enums\TankStatus::Active]);
        $inactiveCpoTank = Tank::factory()->create(['commodity_id' => $cpo->id, 'code' => 'T-INACTIVE-CPO', 'status' => \App\Enums\TankStatus::Inactive]);
        $cpolTank = Tank::factory()->create(['commodity_id' => $cpol->id, 'code' => 'T-CPOL']);
        $vessel = Vessel::factory()->create(['status' => VesselStatus::Arriving]);

        $test = Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Discharges\Pages\CreateDischarge::class)
            ->fillForm([
                'vessel_id' => $vessel->id,
                'commodity_id' => $cpo->id,
            ]);

        $test->assertSee($activeCpoTank->code)
            ->assertDontSee($inactiveCpoTank->code)
            ->assertDontSee($cpolTank->code);
    }

    public function test_discharge_processing_action_visibility_and_backend_enforcement(): void
    {
        $user = User::factory()->create();
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 1000, 'current_quantity' => 0]);
        $vessel = Vessel::factory()->create(['status' => VesselStatus::Scheduled]);
        $discharge = Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'vessel_id' => $vessel->id,
            'status' => DischargeStatus::Draft,
        ]);
        DischargeLog::factory()->create([
            'discharge_id' => $discharge->id,
            'external_event_id' => 'evt-vis',
            'occurred_at' => now()->subMinute(),
            'gross_mass' => 100,
        ]);

        Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Discharges\Pages\ViewDischarge::class, ['record' => $discharge->getKey()])
            ->assertActionVisible('process')
            ->assertActionDisabled('process');

        $vessel->forceFill(['status' => VesselStatus::Arriving])->save();

        Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Discharges\Pages\ViewDischarge::class, ['record' => $discharge->getKey()])
            ->assertActionVisible('process')
            ->assertActionEnabled('process');

        $completedDischarge = Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'vessel_id' => $vessel->id,
            'status' => DischargeStatus::Completed,
        ]);

        Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Discharges\Pages\ViewDischarge::class, ['record' => $completedDischarge->getKey()])
            ->assertActionHidden('process');
    }

    public function test_dispatch_processing_action_visibility_and_backend_enforcement(): void
    {
        $user = User::factory()->create();
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 1000, 'current_quantity' => 10]);
        $dispatch = TruckDispatch::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'quantity' => 5,
            'status' => TruckDispatchStatus::Planned,
            'truck_registration' => 'KAA 123A',
            'driver_name' => 'Driver',
        ]);

        Livewire::actingAs($user)
            ->test(\App\Filament\Resources\TruckDispatches\Pages\ViewTruckDispatch::class, ['record' => $dispatch->getKey()])
            ->assertActionVisible('process')
            ->assertActionEnabled('process');

        $insufficientDispatch = TruckDispatch::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'quantity' => 20,
            'status' => TruckDispatchStatus::Planned,
            'truck_registration' => 'KAA 123B',
            'driver_name' => 'Driver',
        ]);

        Livewire::actingAs($user)
            ->test(\App\Filament\Resources\TruckDispatches\Pages\ViewTruckDispatch::class, ['record' => $insufficientDispatch->getKey()])
            ->assertActionVisible('process')
            ->assertActionDisabled('process');

        $this->expectException(InsufficientTankInventory::class);

        app(ProcessTruckDispatch::class)->execute($insufficientDispatch->fresh(['tank', 'commodity']));
    }

    public function test_relation_managers_show_only_related_records(): void
    {
        $user = User::factory()->create();
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 1000, 'current_quantity' => 100]);
        $otherTank = Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 1000, 'current_quantity' => 100, 'code' => 'T-OTHER']);
        $vessel = Vessel::factory()->create(['status' => VesselStatus::Arriving]);
        $otherVessel = Vessel::factory()->create(['name' => 'MV Other']);
        $discharge = Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'vessel_id' => $vessel->id,
            'status' => DischargeStatus::Draft,
            'reference' => 'DIS-RELATED',
        ]);
        $otherDischarge = Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $otherTank->id,
            'vessel_id' => $otherVessel->id,
            'status' => DischargeStatus::Draft,
            'reference' => 'DIS-UNRELATED',
        ]);
        DischargeLog::factory()->create([
            'discharge_id' => $discharge->id,
            'external_event_id' => 'evt-related',
            'occurred_at' => now()->subMinute(),
            'gross_mass' => 100,
        ]);
        $movement = InventoryMovement::factory()->create([
            'tank_id' => $tank->id,
            'commodity_id' => $commodity->id,
            'movement_type' => InventoryMovementType::Inbound,
            'quantity' => 10,
            'balance_before' => 0,
            'balance_after' => 10,
            'occurred_at' => now(),
            'reference' => 'IM-RELATED',
        ]);
        $otherMovement = InventoryMovement::factory()->create([
            'tank_id' => $otherTank->id,
            'commodity_id' => $commodity->id,
            'movement_type' => InventoryMovementType::Inbound,
            'quantity' => 10,
            'balance_before' => 0,
            'balance_after' => 10,
            'occurred_at' => now(),
            'reference' => 'IM-UNRELATED',
        ]);

        $this->assertTrue($vessel->discharges()->where('reference', $discharge->reference)->exists());
        $this->assertFalse($vessel->discharges()->where('reference', $otherDischarge->reference)->exists());

        Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Vessels\Pages\ViewVessel::class, ['record' => $vessel->getKey()])
            ->assertSuccessful()
            ->assertSee('Discharges');

        $this->assertTrue($discharge->logs()->where('external_event_id', 'evt-related')->exists());

        Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Discharges\Pages\ViewDischarge::class, ['record' => $discharge->getKey()])
            ->assertSuccessful()
            ->assertSee('Source logs');

        $this->assertTrue($tank->inventoryMovements()->where('reference', $movement->reference)->exists());
        $this->assertFalse($tank->inventoryMovements()->where('reference', $otherMovement->reference)->exists());

        Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Tanks\Pages\ViewTank::class, ['record' => $tank->getKey()])
            ->assertSuccessful();
    }

    public function test_movement_detail_is_read_only(): void
    {
        $user = User::factory()->create();
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id]);
        $movement = InventoryMovement::factory()->create([
            'tank_id' => $tank->id,
            'commodity_id' => $commodity->id,
            'movement_type' => InventoryMovementType::Inbound,
            'quantity' => 10,
            'balance_before' => 0,
            'balance_after' => 10,
            'occurred_at' => now(),
        ]);

        Livewire::actingAs($user)
            ->test(\App\Filament\Resources\InventoryMovements\Pages\ViewInventoryMovement::class, ['record' => $movement->getKey()])
            ->assertActionDoesNotExist('edit')
            ->assertActionDoesNotExist('delete')
            ->assertActionDoesNotExist('create');
    }

    public function test_stale_frontend_selection_cannot_bypass_backend_validation(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $otherCommodity = Commodity::factory()->create(['code' => CommodityCode::CPOL]);
        $tank = Tank::factory()->create(['commodity_id' => $otherCommodity->id, 'capacity' => 1000, 'current_quantity' => 0]);
        $vessel = Vessel::factory()->create(['status' => VesselStatus::Arriving]);
        $discharge = Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'vessel_id' => $vessel->id,
            'status' => DischargeStatus::Draft,
        ]);
        DischargeLog::factory()->create([
            'discharge_id' => $discharge->id,
            'external_event_id' => 'evt-stale',
            'occurred_at' => now()->subMinute(),
            'gross_mass' => 100,
        ]);

        $this->expectException(CommodityMismatch::class);

        app(ProcessInboundDischarge::class)->execute($discharge->fresh(['logs', 'tank', 'commodity', 'vessel']));
    }
}
