<?php

namespace Tests\Feature;

use App\Domain\Cargo\Exceptions\InvalidCargoState;
use App\Domain\Cargo\Exceptions\InvalidChronology;
use App\Enums\CommodityCode;
use App\Enums\DischargeStatus;
use App\Enums\TruckDispatchStatus;
use App\Models\Commodity;
use App\Models\Discharge;
use App\Models\DischargeLog;
use App\Models\InventoryMovement;
use App\Models\Tank;
use App\Models\TruckDispatch;
use App\Models\Vessel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Sprint1ARemediationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_movement_cannot_be_updated(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id]);
        $movement = InventoryMovement::factory()->create([
            'tank_id' => $tank->id,
            'commodity_id' => $commodity->id,
            'movement_type' => \App\Enums\InventoryMovementType::Inbound,
            'quantity' => 10,
            'balance_before' => 0,
            'balance_after' => 10,
            'occurred_at' => now(),
        ]);

        $this->expectException(InvalidCargoState::class);

        $movement->quantity = 20;
        $movement->save();
    }

    public function test_inventory_movement_cannot_be_deleted(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id]);
        $movement = InventoryMovement::factory()->create([
            'tank_id' => $tank->id,
            'commodity_id' => $commodity->id,
            'movement_type' => \App\Enums\InventoryMovementType::Inbound,
            'quantity' => 10,
            'balance_before' => 0,
            'balance_after' => 10,
            'occurred_at' => now(),
        ]);

        $this->expectException(InvalidCargoState::class);

        $movement->delete();
    }

    public function test_future_discharge_log_timestamp_is_rejected(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id]);
        $vessel = Vessel::factory()->create(['status' => \App\Enums\VesselStatus::Arriving]);
        $discharge = Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'vessel_id' => $vessel->id,
            'status' => DischargeStatus::Draft,
        ]);

        $this->expectException(InvalidChronology::class);

        DischargeLog::create([
            'discharge_id' => $discharge->id,
            'external_event_id' => 'future-log',
            'occurred_at' => now()->addDay(),
            'gross_mass' => 100,
        ]);
    }

    public function test_late_event_after_completion_is_not_applied(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 1000, 'current_quantity' => 0]);
        $vessel = Vessel::factory()->create(['status' => \App\Enums\VesselStatus::Arriving]);
        $discharge = Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'vessel_id' => $vessel->id,
            'status' => DischargeStatus::Draft,
        ]);
        DischargeLog::factory()->create([
            'discharge_id' => $discharge->id,
            'external_event_id' => 'evt-before',
            'occurred_at' => now()->subMinutes(5),
            'gross_mass' => 100,
        ]);

        app(\App\Actions\ProcessInboundDischarge::class)->execute($discharge->fresh(['logs', 'tank', 'commodity', 'vessel']));

        $lateLog = DischargeLog::create([
            'discharge_id' => $discharge->id,
            'external_event_id' => 'evt-late',
            'occurred_at' => now()->subMinutes(10),
            'gross_mass' => 50,
            'processed_at' => null,
        ]);

        $this->expectException(InvalidChronology::class);

        app(\App\Actions\ProcessInboundDischarge::class)->execute($discharge->fresh(['logs', 'tank', 'commodity', 'vessel']));
    }

    public function test_invalid_truck_registration_is_rejected(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id]);

        $this->expectException(InvalidCargoState::class);

        TruckDispatch::create([
            'truck_registration' => '   ',
            'driver_name' => 'Driver',
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'quantity' => 10,
            'status' => TruckDispatchStatus::Planned,
        ]);
    }
}
