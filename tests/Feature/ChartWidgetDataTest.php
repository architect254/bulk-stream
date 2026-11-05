<?php

namespace Tests\Feature;

use App\Application\Dashboard\TerminalDashboardReadModel;
use App\Enums\CommodityCode;
use App\Enums\DischargeStatus;
use App\Enums\TankStatus;
use App\Enums\TruckDispatchStatus;
use App\Filament\Widgets\CargoFlowChart;
use App\Filament\Widgets\CommodityThroughputChart;
use App\Filament\Widgets\DispatchVolumeChart;
use App\Filament\Widgets\InventoryByCommodityChart;
use App\Filament\Widgets\ReceivedMassChart;
use App\Filament\Widgets\TankUtilizationChart;
use App\Models\Commodity;
use App\Models\Discharge;
use App\Models\Tank;
use App\Models\TruckDispatch;
use App\Support\OperationalTime;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ReadsChartData;
use Tests\TestCase;

class ChartWidgetDataTest extends TestCase
{
    use ReadsChartData;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_cargo_flow_chart_plots_seven_operational_days_and_ignores_incomplete_operations(): void
    {
        $this->freezeAt('2026-11-04 10:00:00');

        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create([
            'commodity_id' => $commodity->id,
            'capacity' => 30000,
            'current_quantity' => 0,
        ]);

        $this->completedDischarge($commodity, $tank, '2026-11-04 09:00:00', 1000, 995);
        $this->completedDischarge($commodity, $tank, '2026-11-02 12:00:00', 500, 497.5);

        Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => DischargeStatus::Processing,
            'gross_mass' => 4000,
            'net_received_mass' => 3980,
            'completed_at' => $this->nairobi('2026-11-03 12:00:00'),
        ]);

        $this->dispatchedTruck($commodity, $tank, '2026-11-03 12:00:00', 100);

        TruckDispatch::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => TruckDispatchStatus::Planned,
            'quantity' => 999,
            'dispatched_at' => $this->nairobi('2026-11-03 13:00:00'),
        ]);

        $data = $this->chartData(app(CargoFlowChart::class));

        $this->assertSame(
            ['29 Oct', '30 Oct', '31 Oct', '1 Nov', '2 Nov', '3 Nov', '4 Nov'],
            $data['labels'],
        );
        $this->assertSame(['Inbound net (MT)', 'Outbound (MT)'], array_column($data['datasets'], 'label'));
        $this->assertSame([0.0, 0.0, 0.0, 0.0, 497.5, 0.0, 995.0], $data['datasets'][0]['data']);
        $this->assertSame([0.0, 0.0, 0.0, 0.0, 0.0, 100.0, 0.0], $data['datasets'][1]['data']);
    }

    public function test_cargo_flow_chart_attributes_activity_to_the_nairobi_operational_day(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-05 10:00:00', OperationalTime::timezone()));

        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 30000, 'current_quantity' => 0]);

        // 22:30 UTC on 4 November is 01:30 on 5 November in Nairobi.
        $this->completedDischarge($commodity, $tank, '2026-11-04 22:30:00', 1000, 995, 'UTC');

        $data = $this->chartData(app(CargoFlowChart::class));

        $this->assertSame('5 Nov', $data['labels'][array_key_last($data['labels'])]);
        $this->assertSame([0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 995.0], $data['datasets'][0]['data']);
    }

    public function test_cargo_flow_chart_shows_the_empty_state_without_completed_movements(): void
    {
        $this->freezeAt('2026-11-04 10:00:00');

        $widget = app(CargoFlowChart::class);

        $this->assertTrue($widget->isEmpty());

        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 30000, 'current_quantity' => 0]);
        $this->completedDischarge($commodity, $tank, '2026-11-04 09:00:00', 1000, 995);

        $this->assertFalse(app(CargoFlowChart::class)->isEmpty());
    }

    public function test_inventory_chart_uses_active_tank_balances_grouped_by_commodity(): void
    {
        $cpo = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $cpol = Commodity::factory()->create(['code' => CommodityCode::CPOL]);
        $pfad = Commodity::factory()->create(['code' => CommodityCode::PFAD]);

        Tank::factory()->create(['code' => 'T-101', 'commodity_id' => $cpo->id, 'capacity' => 30000, 'current_quantity' => 1000]);
        Tank::factory()->create(['code' => 'T-102', 'commodity_id' => $cpo->id, 'capacity' => 25000, 'current_quantity' => 250]);
        Tank::factory()->create(['code' => 'T-201', 'commodity_id' => $cpol->id, 'capacity' => 20000, 'current_quantity' => 500]);
        Tank::factory()->create(['code' => 'T-301', 'commodity_id' => $pfad->id, 'capacity' => 15000, 'current_quantity' => 0]);
        Tank::factory()->create([
            'code' => 'T-999',
            'commodity_id' => $cpo->id,
            'capacity' => 30000,
            'current_quantity' => 5000,
            'status' => TankStatus::Inactive,
        ]);

        $data = $this->chartData(app(InventoryByCommodityChart::class));

        $this->assertSame(['CPO', 'CPOL', 'PFAD'], $data['labels']);
        $this->assertSame([1250.0, 500.0, 0.0], $data['datasets'][0]['data']);

        $inventory = app(TerminalDashboardReadModel::class)->inventory();
        $this->assertSame((float) $inventory['current_quantity'], array_sum($data['datasets'][0]['data']));
    }

    public function test_inventory_chart_shows_the_empty_state_when_active_tanks_hold_nothing(): void
    {
        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 30000, 'current_quantity' => 0]);

        $this->assertTrue(app(InventoryByCommodityChart::class)->isEmpty());
    }

    public function test_received_mass_chart_respects_the_selected_period(): void
    {
        $this->freezeAt('2026-11-04 10:00:00');

        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 30000, 'current_quantity' => 0]);

        $this->completedDischarge($commodity, $tank, '2026-11-04 09:00:00', 1000, 995);
        $this->completedDischarge($commodity, $tank, '2026-11-01 09:00:00', 2000, 1990);

        Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => DischargeStatus::Draft,
            'gross_mass' => 5000,
            'transit_loss_mass' => 25,
            'net_received_mass' => 4975,
        ]);

        $today = app(ReceivedMassChart::class);
        $today->period = 'today';
        $todayData = $this->chartData($today);

        $this->assertSame(['4 Nov'], $todayData['labels']);
        $this->assertSame([1000.0], $todayData['datasets'][0]['data']);
        $this->assertSame([995.0], $todayData['datasets'][1]['data']);

        $week = app(ReceivedMassChart::class);
        $week->period = 'last_7_days';
        $weekData = $this->chartData($week);

        $this->assertCount(7, $weekData['labels']);
        $this->assertSame(2985.0, array_sum($weekData['datasets'][1]['data']));
        $this->assertSame(3000.0, array_sum($weekData['datasets'][0]['data']));
    }

    public function test_received_mass_chart_shows_the_empty_state_without_completed_discharges(): void
    {
        $this->freezeAt('2026-11-04 10:00:00');

        $this->assertTrue(app(ReceivedMassChart::class)->isEmpty());
    }

    public function test_dispatch_volume_chart_is_a_line_chart_grouped_by_dispatch_time(): void
    {
        $this->freezeAt('2026-11-04 10:00:00');

        $commodity = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $tank = Tank::factory()->create(['commodity_id' => $commodity->id, 'capacity' => 30000, 'current_quantity' => 1000]);

        $this->dispatchedTruck($commodity, $tank, '2026-11-04 08:00:00', 100);
        $this->dispatchedTruck($commodity, $tank, '2026-11-03 08:00:00', 40);

        TruckDispatch::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => TruckDispatchStatus::Planned,
            'quantity' => 900,
            'dispatched_at' => $this->nairobi('2026-11-03 09:00:00'),
        ]);

        $widget = app(DispatchVolumeChart::class);
        $widget->period = 'last_7_days';
        $data = $this->chartData($widget);

        $this->assertSame('line', $this->chartType($widget));
        $this->assertCount(7, $data['labels']);
        $this->assertSame(140.0, array_sum($data['datasets'][0]['data']));
        $this->assertSame(40.0, $data['datasets'][0]['data'][5]);
        $this->assertSame(100.0, $data['datasets'][0]['data'][6]);
    }

    public function test_dispatch_volume_chart_shows_the_empty_state_without_completed_dispatches(): void
    {
        $this->freezeAt('2026-11-04 10:00:00');

        $this->assertTrue(app(DispatchVolumeChart::class)->isEmpty());
    }

    public function test_tank_utilization_chart_compares_current_stock_with_remaining_capacity(): void
    {
        $cpo = Commodity::factory()->create(['code' => CommodityCode::CPO]);

        Tank::factory()->create(['code' => 'T-101', 'commodity_id' => $cpo->id, 'capacity' => 30000, 'current_quantity' => 7500]);
        Tank::factory()->create(['code' => 'T-102', 'commodity_id' => $cpo->id, 'capacity' => 25000, 'current_quantity' => 0]);
        Tank::factory()->create([
            'code' => 'T-999',
            'commodity_id' => $cpo->id,
            'capacity' => 10000,
            'current_quantity' => 0,
            'status' => TankStatus::Inactive,
        ]);

        $data = $this->chartData(app(TankUtilizationChart::class));

        $this->assertSame(['T-101', 'T-102'], $data['labels']);
        $this->assertSame([7500.0, 0.0], $data['datasets'][0]['data']);
        $this->assertSame([22500.0, 25000.0], $data['datasets'][1]['data']);
        $this->assertSame([30000.0, 25000.0], $data['datasets'][0]['capacity']);

        foreach ($data['labels'] as $index => $code) {
            $this->assertSame(
                $data['datasets'][0]['capacity'][$index],
                $data['datasets'][0]['data'][$index] + $data['datasets'][1]['data'][$index],
                'Current plus available must equal the configured capacity for '.$code,
            );
        }
    }

    public function test_tank_utilization_chart_shows_the_empty_state_without_active_tanks(): void
    {
        $cpo = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        Tank::factory()->create([
            'commodity_id' => $cpo->id,
            'capacity' => 30000,
            'current_quantity' => 0,
            'status' => TankStatus::Inactive,
        ]);

        $this->assertTrue(app(TankUtilizationChart::class)->isEmpty());
    }

    public function test_commodity_chart_separates_period_throughput_from_current_stock(): void
    {
        $this->freezeAt('2026-11-04 10:00:00');

        $cpo = Commodity::factory()->create(['code' => CommodityCode::CPO]);
        $cpol = Commodity::factory()->create(['code' => CommodityCode::CPOL]);

        $cpoTank = Tank::factory()->create(['code' => 'T-101', 'commodity_id' => $cpo->id, 'capacity' => 30000, 'current_quantity' => 995]);
        $cpolTank = Tank::factory()->create(['code' => 'T-201', 'commodity_id' => $cpol->id, 'capacity' => 20000, 'current_quantity' => 100]);

        $this->completedDischarge($cpo, $cpoTank, '2026-11-04 09:00:00', 1000, 995);
        $this->dispatchedTruck($cpol, $cpolTank, '2026-11-03 09:00:00', 25);

        $widget = app(CommodityThroughputChart::class);
        $widget->period = 'today';
        $data = $this->chartData($widget);

        $this->assertSame(['CPO', 'CPOL'], $data['labels']);
        $this->assertSame(
            ['Period received net (MT)', 'Period dispatched (MT)', 'Current stock (MT)'],
            array_column($data['datasets'], 'label'),
        );
        $this->assertSame([995.0, 0.0], $data['datasets'][0]['data']);
        $this->assertSame([0.0, 0.0], $data['datasets'][1]['data'], 'Yesterday\'s dispatch is outside today\'s period.');
        $this->assertSame([995.0, 100.0], $data['datasets'][2]['data'], 'Current stock is a current-state measure.');
    }

    public function test_commodity_chart_shows_the_empty_state_when_all_series_are_zero(): void
    {
        $this->freezeAt('2026-11-04 10:00:00');

        $this->assertTrue(app(CommodityThroughputChart::class)->isEmpty());
    }

    private function completedDischarge(
        Commodity $commodity,
        Tank $tank,
        string $completedAt,
        float $gross,
        float $net,
        string $timezone = OperationalTime::TIMEZONE,
    ): Discharge {
        return Discharge::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => DischargeStatus::Completed,
            'gross_mass' => $gross,
            'transit_loss_mass' => $gross - $net,
            'net_received_mass' => $net,
            'completed_at' => CarbonImmutable::parse($completedAt, $timezone),
        ]);
    }

    private function dispatchedTruck(Commodity $commodity, Tank $tank, string $dispatchedAt, float $quantity): TruckDispatch
    {
        return TruckDispatch::factory()->create([
            'commodity_id' => $commodity->id,
            'tank_id' => $tank->id,
            'status' => TruckDispatchStatus::Dispatched,
            'quantity' => $quantity,
            'dispatched_at' => $this->nairobi($dispatchedAt),
        ]);
    }

    private function nairobi(string $datetime): CarbonImmutable
    {
        return CarbonImmutable::parse($datetime, OperationalTime::timezone());
    }

    private function freezeAt(string $datetime): void
    {
        CarbonImmutable::setTestNow($this->nairobi($datetime));
    }
}
