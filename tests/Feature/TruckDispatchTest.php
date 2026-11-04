<?php

namespace Tests\Feature;

use App\Actions\ProcessTruckDispatch;
use App\Domain\Cargo\Exceptions\CommodityMismatch;
use App\Domain\Cargo\Exceptions\InsufficientTankInventory;
use App\Domain\Cargo\Exceptions\InvalidCargoQuantity;
use App\Domain\Cargo\Exceptions\InvalidStateTransition;
use App\Enums\CommodityCode;
use App\Enums\InventoryMovementType;
use App\Enums\TruckDispatchStatus;
use App\Models\Commodity;
use App\Models\InventoryMovement;
use App\Models\Tank;
use App\Models\TruckDispatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TruckDispatchTest extends TestCase
{
    use RefreshDatabase;

    private function makeDispatch(float|int|string $quantity, array $overrides = []): TruckDispatch
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(array_merge([
            'commodity_id' => $commodity->id,
            'capacity' => 1000,
            'current_quantity' => $overrides['current_quantity'] ?? 100,
        ], $overrides['tank'] ?? []));

        return TruckDispatch::factory()->create(array_merge([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'quantity' => $quantity,
            'status' => TruckDispatchStatus::Planned,
        ], $overrides['dispatch'] ?? []));
    }

    public function test_happy_path_dispatches_truck(): void
    {
        $dispatch = $this->makeDispatch(40);

        $processed = app(ProcessTruckDispatch::class)->execute($dispatch);

        $this->assertSame(TruckDispatchStatus::Dispatched, $processed->status);
        $this->assertSame('60.000', (string) Tank::find($dispatch->tank_id)->current_quantity);

        $movement = InventoryMovement::where('source_id', $dispatch->id)->firstOrFail();
        $this->assertSame(InventoryMovementType::Outbound, $movement->movement_type);
        $this->assertSame('40.000', (string) $movement->quantity);
        $this->assertSame('100.000', (string) $movement->balance_before);
        $this->assertSame('60.000', (string) $movement->balance_after);
    }

    public function test_exact_inventory_boundary_succeeds(): void
    {
        $dispatch = $this->makeDispatch(100);

        app(ProcessTruckDispatch::class)->execute($dispatch);

        $this->assertSame('0.000', (string) Tank::find($dispatch->tank_id)->current_quantity);
    }

    public function test_insufficient_inventory_fails_without_mutation(): void
    {
        $dispatch = $this->makeDispatch('100.001');

        try {
            app(ProcessTruckDispatch::class)->execute($dispatch);
            $this->fail('Expected InsufficientTankInventory.');
        } catch (InsufficientTankInventory $e) {
            $this->assertTrue(true);
        }

        $this->assertSame(TruckDispatchStatus::Planned, $dispatch->fresh()->status);
        $this->assertSame('100.000', (string) Tank::find($dispatch->tank_id)->current_quantity);
        $this->assertSame(0, InventoryMovement::count());
    }

    public function test_commodity_mismatch_is_rejected(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $otherCommodity = Commodity::factory()->create(['code' => CommodityCode::CPOL]);
        $tank = Tank::factory()->create([
            'commodity_id' => $otherCommodity->id,
            'capacity' => 1000,
            'current_quantity' => 100,
        ]);
        $dispatch = TruckDispatch::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'quantity' => 10,
            'status' => TruckDispatchStatus::Planned,
        ]);

        $this->expectException(CommodityMismatch::class);

        app(ProcessTruckDispatch::class)->execute($dispatch->fresh(['tank', 'commodity']));
    }

    public function test_zero_quantity_is_rejected(): void
    {
        $dispatch = $this->makeDispatch(0);

        $this->expectException(InvalidCargoQuantity::class);

        app(ProcessTruckDispatch::class)->execute($dispatch);
    }

    public function test_negative_quantity_is_rejected(): void
    {
        $dispatch = $this->makeDispatch(-5);

        $this->expectException(InvalidCargoQuantity::class);

        app(ProcessTruckDispatch::class)->execute($dispatch);
    }

    public function test_idempotent_second_call_does_not_double_deduct(): void
    {
        $dispatch = $this->makeDispatch(40);
        $service = app(ProcessTruckDispatch::class);

        $first = $service->execute($dispatch);
        $second = $service->execute($first->fresh(['tank', 'commodity']));

        $this->assertSame(TruckDispatchStatus::Dispatched, $second->status);
        $this->assertSame('60.000', (string) Tank::find($dispatch->tank_id)->current_quantity);
        $this->assertSame(1, InventoryMovement::count());
    }

    public function test_cancelled_dispatch_is_rejected(): void
    {
        $dispatch = $this->makeDispatch(10, ['dispatch' => ['status' => TruckDispatchStatus::Cancelled]]);

        $this->expectException(InvalidStateTransition::class);

        app(ProcessTruckDispatch::class)->execute($dispatch);
    }

    public function test_already_dispatched_does_not_deduct_again(): void
    {
        $dispatch = $this->makeDispatch(40);
        $service = app(ProcessTruckDispatch::class);

        $service->execute($dispatch);

        $dispatched = TruckDispatch::find($dispatch->id);
        $service->execute($dispatched->fresh(['tank', 'commodity']));

        $this->assertSame('60.000', (string) Tank::find($dispatch->tank_id)->current_quantity);
        $this->assertSame(1, InventoryMovement::count());
    }

    public function test_competing_dispatches_respect_inventory(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 1000,
            'current_quantity' => 1000,
        ]);

        $dispatchOne = TruckDispatch::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'quantity' => 700,
            'status' => TruckDispatchStatus::Planned,
        ]);

        $dispatchTwo = TruckDispatch::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'quantity' => 500,
            'status' => TruckDispatchStatus::Planned,
        ]);

        $service = app(ProcessTruckDispatch::class);

        $service->execute($dispatchOne->fresh(['tank', 'commodity']));

        try {
            $service->execute($dispatchTwo->fresh(['tank', 'commodity']));
            $this->fail('Expected InsufficientTankInventory.');
        } catch (InsufficientTankInventory $e) {
            $this->assertTrue(true);
        }

        $this->assertSame('300.000', (string) Tank::find($tank->id)->current_quantity);
        $this->assertSame(1, InventoryMovement::count());
    }
}
