<?php

namespace Tests\Feature;

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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_data_is_created(): void
    {
        $this->seed();

        $this->assertSame(3, Commodity::count());
        $this->assertSame(4, Tank::count());
        $this->assertSame(3, Vessel::count());

        $this->assertDatabaseHas('commodities', ['code' => 'CPO', 'name' => 'Crude Palm Oil']);
        $this->assertDatabaseHas('tanks', ['code' => 'T-101']);
    }

    public function test_model_relationships_resolve(): void
    {
        $commodity = Commodity::factory()->create();
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id]);
        $vessel = Vessel::factory()->create();

        $discharge = Discharge::factory()->create([
            'vessel_id' => $vessel->id,
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
        ]);

        $log = DischargeLog::factory()->create(['discharge_id' => $discharge->id]);

        $dispatch = TruckDispatch::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
        ]);

        $movement = InventoryMovement::factory()->create([
            'tank_id' => $tank->id,
            'commodity_id' => $commodity->id,
        ]);

        $this->assertTrue($tank->commodity->is($commodity));
        $this->assertTrue($discharge->vessel->is($vessel));
        $this->assertTrue($discharge->commodity->is($commodity));
        $this->assertTrue($discharge->tank->is($tank));
        $this->assertTrue($log->discharge->is($discharge));
        $this->assertTrue($dispatch->commodity->is($commodity));
        $this->assertTrue($dispatch->tank->is($tank));
        $this->assertTrue($movement->tank->is($tank));
        $this->assertTrue($movement->commodity->is($commodity));

        $this->assertCount(1, $commodity->tanks);
        $this->assertCount(1, $vessel->discharges);
        $this->assertCount(1, $discharge->logs);
    }

    public function test_enum_casting(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::PFAD]);
        $vessel = Vessel::factory()->create(['status' => VesselStatus::Arriving]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id, 'status' => TankStatus::Inactive]);
        $discharge = Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'vessel_id' => $vessel->id,
            'status' => DischargeStatus::Processing,
        ]);
        $dispatch = TruckDispatch::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => TruckDispatchStatus::Loading,
        ]);
        $movement = InventoryMovement::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'movement_type' => InventoryMovementType::Outbound,
        ]);

        $this->assertSame(VesselStatus::Arriving, $vessel->fresh()->status);
        $this->assertSame(TankStatus::Inactive, $tank->fresh()->status);
        $this->assertSame(DischargeStatus::Processing, $discharge->fresh()->status);
        $this->assertSame(TruckDispatchStatus::Loading, $dispatch->fresh()->status);
        $this->assertSame(InventoryMovementType::Outbound, $movement->fresh()->movement_type);
        $this->assertSame(CommodityCode::PFAD, $commodity->fresh()->code);
    }

    public function test_duplicate_commodity_code_is_rejected(): void
    {
        Commodity::factory()->create(['code' => CommodityCode::CPO]);

        $this->expectException(QueryException::class);

        Commodity::factory()->create(['code' => CommodityCode::CPO]);
    }

    public function test_duplicate_tank_code_is_rejected(): void
    {
        Tank::factory()->create(['code' => 'T-999']);

        $this->expectException(QueryException::class);

        Tank::factory()->create(['code' => 'T-999']);
    }

    public function test_duplicate_discharge_reference_is_rejected(): void
    {
        $commodity = Commodity::factory()->create();
        $vessel = Vessel::factory()->create();
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id]);

        Discharge::factory()->create([
            'reference' => 'DIS-1234',
            'commodity_id' => $commodity->id,
            'vessel_id' => $vessel->id,
            'tank_id' => $tank->id,
        ]);

        $this->expectException(QueryException::class);

        Discharge::factory()->create([
            'reference' => 'DIS-1234',
            'commodity_id' => $commodity->id,
            'vessel_id' => $vessel->id,
            'tank_id' => $tank->id,
        ]);
    }

    public function test_duplicate_external_event_id_is_rejected(): void
    {
        $commodity = Commodity::factory()->create();
        $vessel = Vessel::factory()->create();
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id]);
        $discharge = Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'vessel_id' => $vessel->id,
            'tank_id' => $tank->id,
        ]);

        DischargeLog::factory()->create([
            'discharge_id' => $discharge->id,
            'external_event_id' => 'evt-1',
        ]);

        $this->expectException(QueryException::class);

        DischargeLog::factory()->create([
            'discharge_id' => $discharge->id,
            'external_event_id' => 'evt-1',
        ]);
    }
}
