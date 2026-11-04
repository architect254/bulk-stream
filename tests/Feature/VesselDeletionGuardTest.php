<?php

namespace Tests\Feature;

use App\Domain\Cargo\Exceptions\InvalidCargoState;
use App\Enums\CommodityCode;
use App\Enums\DischargeStatus;
use App\Enums\TruckDispatchStatus;
use App\Enums\VesselStatus;
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

class VesselDeletionGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_vessel_without_discharge_history_can_be_deleted(): void
    {
        $vessel = Vessel::factory()->create();

        $vessel->delete();

        $this->assertDatabaseMissing('vessels', ['id' => $vessel->id]);
    }

    public function test_vessel_with_discharge_history_cannot_be_deleted(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id]);
        $vessel = Vessel::factory()->create(['status' => VesselStatus::Arriving]);
        $discharge = Discharge::factory()->create([
            'vessel_id' => $vessel->id,
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => DischargeStatus::Draft,
        ]);

        $this->expectException(InvalidCargoState::class);

        $vessel->delete();

        $this->assertDatabaseHas('vessels', ['id' => $vessel->id]);
        $this->assertDatabaseHas('discharges', ['id' => $discharge->id]);
    }

    public function test_bulk_deletion_cannot_bypass_the_guard(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id]);

        $protected = Vessel::factory()->create(['status' => VesselStatus::Arriving]);
        Discharge::factory()->create([
            'vessel_id' => $protected->id,
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => DischargeStatus::Draft,
        ]);

        $deletable = Vessel::factory()->create();

        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Vessels\Pages\ListVessels::class)
            ->callTableBulkAction('delete', [$protected, $deletable])
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('vessels', ['id' => $deletable->id]);
        $this->assertDatabaseHas('vessels', ['id' => $protected->id]);
    }
}
