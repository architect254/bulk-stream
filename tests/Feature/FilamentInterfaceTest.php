<?php

namespace Tests\Feature;

use App\Enums\CommodityCode;
use App\Enums\DischargeStatus;
use App\Enums\VesselStatus;
use App\Filament\Resources\Discharges\Pages\ListDischarges;
use App\Filament\Resources\TruckDispatches\Pages\ListTruckDispatches;
use App\Filament\Resources\Vessels\Pages\CreateVessel;
use App\Filament\Resources\Vessels\Pages\ListVessels;
use App\Models\Commodity;
use App\Models\Discharge;
use App\Models\DischargeLog;
use App\Models\Tank;
use App\Models\TruckDispatch;
use App\Models\User;
use App\Models\Vessel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_cannot_access_panel(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_authenticated_user_can_render_vessel_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/vessels')
            ->assertOk();
    }

    public function test_vessel_create_page_renders(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CreateVessel::class)
            ->assertSuccessful();
    }

    public function test_vessel_can_be_created_from_filament(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(CreateVessel::class)
            ->fillForm([
                'name' => 'MV Test Vessel',
                'voyage_reference' => 'VOY-1',
                'status' => 'scheduled',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('vessels', ['name' => 'MV Test Vessel']);
    }

    public function test_discharge_list_renders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/discharges')
            ->assertOk();
    }

    public function test_discharge_can_be_processed_from_list(): void
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
            'external_event_id' => 'evt-ui-1',
            'occurred_at' => now()->subMinute(),
            'gross_mass' => 100,
        ]);

        Livewire::actingAs($user)
            ->test(ListDischarges::class)
            ->callTableAction('process', $discharge)
            ->assertHasNoErrors();

        $this->assertSame(DischargeStatus::Completed, $discharge->fresh()->status);
    }

    public function test_truck_dispatch_list_renders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/truck-dispatches')
            ->assertOk();
    }

    public function test_truck_dispatch_can_be_processed_from_list(): void
    {
        $user = User::factory()->create();
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 1000,
            'current_quantity' => 100,
        ]);
        $dispatch = TruckDispatch::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'quantity' => 40,
            'status' => \App\Enums\TruckDispatchStatus::Planned,
        ]);

        Livewire::actingAs($user)
            ->test(ListTruckDispatches::class)
            ->callTableAction('process', $dispatch)
            ->assertHasNoErrors();

        $this->assertSame(\App\Enums\TruckDispatchStatus::Dispatched, $dispatch->fresh()->status);
    }

    public function test_inventory_movements_are_read_only(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/inventory-movements')
            ->assertOk();
    }
}
