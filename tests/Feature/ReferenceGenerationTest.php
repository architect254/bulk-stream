<?php

namespace Tests\Feature;

use App\Enums\CommodityCode;
use App\Enums\DischargeStatus;
use App\Enums\TruckDispatchStatus;
use App\Enums\VesselStatus;
use App\Models\Commodity;
use App\Models\Discharge;
use App\Models\Tank;
use App\Models\TruckDispatch;
use App\Models\Vessel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReferenceGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_discharge_reference_is_generated_when_missing(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id]);
        $vessel = Vessel::factory()->create(['status' => VesselStatus::Arriving]);

        $discharge = Discharge::create([
            'vessel_id' => $vessel->id,
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => DischargeStatus::Draft,
        ]);

        $this->assertNotNull($discharge->reference);
        $this->assertDatabaseHas('discharges', ['id' => $discharge->id, 'reference' => $discharge->reference]);
    }

    public function test_truck_dispatch_reference_is_generated_when_missing(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id]);

        $dispatch = TruckDispatch::create([
            'truck_registration' => 'KAA 001A',
            'driver_name' => 'QA Driver',
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'quantity' => 10,
            'status' => TruckDispatchStatus::Planned,
        ]);

        $this->assertNotNull($dispatch->reference);
        $this->assertDatabaseHas('truck_dispatches', ['id' => $dispatch->id, 'reference' => $dispatch->reference]);
    }

    public function test_discharge_can_be_created_from_filament(): void
    {
        $user = \App\Models\User::factory()->create();
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 1000, 'current_quantity' => 0]);
        $vessel = Vessel::factory()->create(['status' => VesselStatus::Arriving]);

        Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Discharges\Pages\CreateDischarge::class)
            ->fillForm([
                'vessel_id' => $vessel->id,
                'commodity_id' => $commodity->id,
                'tank_id' => $tank->id,
                'logs' => [
                    [
                        'external_event_id' => 'evt-ui-create',
                        'occurred_at' => now()->format('Y-m-d H:i'),
                        'gross_mass' => 100,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('discharges', ['vessel_id' => $vessel->id, 'reference' => \DB::raw('reference')]);
    }
}
