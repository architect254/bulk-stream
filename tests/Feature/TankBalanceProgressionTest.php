<?php

namespace Tests\Feature;

use App\Enums\CommodityCode;
use App\Enums\InventoryMovementType;
use App\Filament\Widgets\TankBalanceChart;
use App\Models\Commodity;
use App\Models\InventoryMovement;
use App\Models\Tank;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ReadsChartData;
use Tests\TestCase;

class TankBalanceProgressionTest extends TestCase
{
    use ReadsChartData;
    use RefreshDatabase;

    private function makeTankWithHistory(): Tank
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create([
            'code' => 'T-101',
            'commodity_id' => $commodity->id,
            'capacity' => 30000,
            'current_quantity' => 0,
        ]);

        InventoryMovement::factory()->create([
            'tank_id' => $tank->id,
            'commodity_id' => $commodity->id,
            'movement_type' => InventoryMovementType::Inbound,
            'quantity' => 1000,
            'balance_before' => 0,
            'balance_after' => 1000,
            'occurred_at' => CarbonImmutable::parse('2026-11-04 09:00:00', 'UTC'),
        ]);
        InventoryMovement::factory()->create([
            'tank_id' => $tank->id,
            'commodity_id' => $commodity->id,
            'movement_type' => InventoryMovementType::Outbound,
            'quantity' => 50,
            'balance_before' => 1000,
            'balance_after' => 950,
            'occurred_at' => CarbonImmutable::parse('2026-11-04 10:00:00', 'UTC'),
        ]);
        $tank->forceFill(['current_quantity' => 950])->save();

        return $tank;
    }

    public function test_valid_movement_chain_renders_progression(): void
    {
        $tank = $this->makeTankWithHistory();

        $widget = app(TankBalanceChart::class);
        $widget->tankId = (int) $tank->id;

        $data = $this->chartData($widget);

        $this->assertSame(['Opening balance', '4 Nov 12:00', '4 Nov 13:00'], $data['labels']);
        $this->assertSame([0.0, 1000.0, 950.0], $data['datasets'][0]['data']);
        $this->assertFalse($widget->isEmpty());
        $this->assertStringContainsString('reconciles to current stock', (string) $widget->getDescription());
    }

    public function test_multiple_inbound_and_outbound_movements_keep_chronology_and_ordering(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 30000,
            'current_quantity' => 0,
        ]);

        $sameTime = CarbonImmutable::parse('2026-11-04 09:00:00', 'UTC');

        $first = InventoryMovement::factory()->create([
            'tank_id' => $tank->id,
            'commodity_id' => $commodity->id,
            'movement_type' => InventoryMovementType::Inbound,
            'quantity' => 100,
            'balance_before' => 0,
            'balance_after' => 100,
            'occurred_at' => $sameTime,
        ]);
        $second = InventoryMovement::factory()->create([
            'tank_id' => $tank->id,
            'commodity_id' => $commodity->id,
            'movement_type' => InventoryMovementType::Inbound,
            'quantity' => 50,
            'balance_before' => 100,
            'balance_after' => 150,
            'occurred_at' => $sameTime,
        ]);
        $tank->forceFill(['current_quantity' => 150])->save();

        $widget = app(TankBalanceChart::class);
        $widget->tankId = (int) $tank->id;

        $data = $this->chartData($widget);

        $this->assertSame([0.0, 100.0, 150.0], $data['datasets'][0]['data']);
        $this->assertCount(3, $data['labels']);
    }

    public function test_disconnected_before_balance_is_unverifiable_and_not_charted(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 30000,
            'current_quantity' => 100,
        ]);

        InventoryMovement::factory()->create([
            'tank_id' => $tank->id,
            'commodity_id' => $commodity->id,
            'movement_type' => InventoryMovementType::Inbound,
            'quantity' => 100,
            'balance_before' => 0,
            'balance_after' => 100,
            'occurred_at' => CarbonImmutable::parse('2026-11-04 09:00:00', 'UTC'),
        ]);
        InventoryMovement::factory()->create([
            'tank_id' => $tank->id,
            'commodity_id' => $commodity->id,
            'movement_type' => InventoryMovementType::Outbound,
            'quantity' => 10,
            'balance_before' => 999,
            'balance_after' => 989,
            'occurred_at' => CarbonImmutable::parse('2026-11-04 10:00:00', 'UTC'),
        ]);

        $widget = app(TankBalanceChart::class);
        $widget->tankId = (int) $tank->id;

        $this->assertTrue($widget->isEmpty());
        $this->assertSame('Balance history cannot be verified', $widget->getEmptyStateHeading());
        $this->assertStringContainsString('previous balance', (string) $widget->getEmptyStateDescription());
    }

    public function test_incorrect_movement_arithmetic_is_unverifiable(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 30000,
            'current_quantity' => 100,
        ]);

        InventoryMovement::factory()->create([
            'tank_id' => $tank->id,
            'commodity_id' => $commodity->id,
            'movement_type' => InventoryMovementType::Inbound,
            'quantity' => 100,
            'balance_before' => 0,
            'balance_after' => 999,
            'occurred_at' => CarbonImmutable::parse('2026-11-04 09:00:00', 'UTC'),
        ]);

        $widget = app(TankBalanceChart::class);
        $widget->tankId = (int) $tank->id;

        $this->assertTrue($widget->isEmpty());
        $this->assertSame('Balance history cannot be verified', $widget->getEmptyStateHeading());
    }

    public function test_final_balance_differing_from_tank_stock_is_unverifiable(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 30000,
            'current_quantity' => 500,
        ]);

        InventoryMovement::factory()->create([
            'tank_id' => $tank->id,
            'commodity_id' => $commodity->id,
            'movement_type' => InventoryMovementType::Inbound,
            'quantity' => 100,
            'balance_before' => 0,
            'balance_after' => 100,
            'occurred_at' => CarbonImmutable::parse('2026-11-04 09:00:00', 'UTC'),
        ]);

        $widget = app(TankBalanceChart::class);
        $widget->tankId = (int) $tank->id;

        $this->assertTrue($widget->isEmpty());
        $this->assertStringContainsString('reconstructed balance differs', strtolower((string) $widget->getEmptyStateDescription()));
    }

    public function test_empty_history_with_zero_stock_shows_empty_state(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 30000,
            'current_quantity' => 0,
        ]);

        $widget = app(TankBalanceChart::class);
        $widget->tankId = (int) $tank->id;

        $this->assertTrue($widget->isEmpty());
        $this->assertSame('No inventory movement history', $widget->getEmptyStateHeading());
    }

    public function test_empty_history_with_nonzero_stock_is_unverifiable(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 30000,
            'current_quantity' => 25,
        ]);

        $widget = app(TankBalanceChart::class);
        $widget->tankId = (int) $tank->id;

        $this->assertTrue($widget->isEmpty());
        $this->assertSame('Balance history cannot be verified', $widget->getEmptyStateHeading());
    }

    public function test_chart_does_not_modify_tank_stock(): void
    {
        $tank = $this->makeTankWithHistory();
        $before = (string) $tank->fresh()->current_quantity;

        $widget = app(TankBalanceChart::class);
        $widget->tankId = (int) $tank->id;

        $this->chartData($widget);
        $this->chartType($widget);

        $this->assertSame($before, (string) $tank->fresh()->current_quantity);
    }

    public function test_tank_detail_page_renders_chart(): void
    {
        $tank = $this->makeTankWithHistory();

        $this->actingAs(User::factory()->create())
            ->get('/admin/tanks/'.$tank->id)
            ->assertSuccessful()
            ->assertSee('Inventory balance progression');
    }
}
