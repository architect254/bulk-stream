<?php

namespace Tests\Feature;

use App\Filament\Widgets\CargoFlowChart;
use App\Filament\Widgets\CommodityThroughputChart;
use App\Filament\Widgets\DispatchVolumeChart;
use App\Filament\Widgets\InventoryByCommodityChart;
use App\Filament\Widgets\ReceivedMassChart;
use App\Filament\Widgets\TankUtilizationChart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChartWidgetDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_cargo_flow_chart_data_shape(): void
    {
        $widget = app(CargoFlowChart::class);
        $method = new \ReflectionMethod($widget, 'getData');
        $method->setAccessible(true);
        $data = $method->invoke($widget);

        $this->assertArrayHasKey('labels', $data);
        $this->assertArrayHasKey('datasets', $data);
        $this->assertCount(7, $data['labels']);
        $this->assertCount(2, $data['datasets']);
    }

    public function test_inventory_by_commodity_empty_state(): void
    {
        $widget = app(InventoryByCommodityChart::class);

        $this->assertTrue($widget->isEmpty());
    }

    public function test_received_mass_chart_data_shape(): void
    {
        $widget = app(ReceivedMassChart::class);
        $method = new \ReflectionMethod($widget, 'getData');
        $method->setAccessible(true);
        $data = $method->invoke($widget);

        $this->assertArrayHasKey('labels', $data);
        $this->assertCount(2, $data['datasets']);
    }

    public function test_dispatch_volume_chart_data_shape(): void
    {
        $widget = app(DispatchVolumeChart::class);
        $method = new \ReflectionMethod($widget, 'getData');
        $method->setAccessible(true);
        $data = $method->invoke($widget);

        $this->assertArrayHasKey('labels', $data);
        $this->assertCount(1, $data['datasets']);
    }

    public function test_tank_utilization_chart_data_shape(): void
    {
        $widget = app(TankUtilizationChart::class);
        $method = new \ReflectionMethod($widget, 'getData');
        $method->setAccessible(true);
        $data = $method->invoke($widget);

        $this->assertArrayHasKey('labels', $data);
        $this->assertCount(2, $data['datasets']);
    }

    public function test_commodity_throughput_chart_data_shape(): void
    {
        $widget = app(CommodityThroughputChart::class);
        $method = new \ReflectionMethod($widget, 'getData');
        $method->setAccessible(true);
        $data = $method->invoke($widget);

        $this->assertArrayHasKey('labels', $data);
        $this->assertCount(3, $data['datasets']);
    }
}
