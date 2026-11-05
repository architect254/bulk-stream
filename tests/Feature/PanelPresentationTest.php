<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Discharges\DischargeResource;
use App\Filament\Resources\InventoryMovements\InventoryMovementResource;
use App\Filament\Resources\Tanks\TankResource;
use App\Filament\Resources\TruckDispatches\TruckDispatchResource;
use App\Filament\Resources\Vessels\VesselResource;
use App\Filament\Widgets\CargoFlowChart;
use App\Filament\Widgets\CommodityBreakdownTable;
use App\Filament\Widgets\CommodityThroughputChart;
use App\Filament\Widgets\DischargeAnalysisTable;
use App\Filament\Widgets\DispatchVolumeChart;
use App\Filament\Widgets\InventoryByCommodityChart;
use App\Filament\Widgets\OperationalExceptions;
use App\Filament\Widgets\OperationalFlow;
use App\Filament\Widgets\PeriodSummary;
use App\Filament\Widgets\ReceivedMassChart;
use App\Filament\Widgets\RecentMovements;
use App\Filament\Widgets\ReconciliationTable;
use App\Filament\Widgets\TankBoard;
use App\Filament\Widgets\TankUtilizationChart;
use App\Filament\Widgets\TerminalOverview;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Support\Enums\Width;
use Tests\TestCase;

class PanelPresentationTest extends TestCase
{
    public function test_panel_uses_the_restored_filament_theme_and_full_width_layout(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertSame('resources/css/filament/admin/theme.css', $panel->getViteTheme());
        $this->assertSame(Width::Full, $panel->getMaxContentWidth());
        $this->assertSame('Bulkstream', $panel->getBrandName());
    }

    public function test_panel_keeps_operational_navigation_groups(): void
    {
        $groups = array_map(
            fn (NavigationGroup|string $group): string => $group instanceof NavigationGroup ? $group->getLabel() : $group,
            Filament::getPanel('admin')->getNavigationGroups(),
        );

        $this->assertContains('Operations', $groups);
        $this->assertContains('Inventory', $groups);
    }

    public function test_resources_use_operational_navigation_labels_and_order(): void
    {
        $expected = [
            VesselResource::class => ['Vessels', 'Operations', 10],
            DischargeResource::class => ['Inbound discharges', 'Operations', 20],
            TruckDispatchResource::class => ['Truck dispatches', 'Operations', 30],
            TankResource::class => ['Tank inventory', 'Inventory', 10],
            InventoryMovementResource::class => ['Inventory movements', 'Inventory', 20],
        ];

        foreach ($expected as $resource => [$label, $group, $sort]) {
            $this->assertSame($label, $resource::getNavigationLabel());
            $this->assertSame($group, $resource::getNavigationGroup());
            $this->assertSame($sort, $resource::getNavigationSort());
        }
    }

    public function test_dashboard_registers_the_operational_widget_order(): void
    {
        $dashboard = app(Dashboard::class);

        $this->assertSame([
            TerminalOverview::class,
            OperationalExceptions::class,
            OperationalFlow::class,
            TankBoard::class,
            TankUtilizationChart::class,
            InventoryByCommodityChart::class,
            RecentMovements::class,
            CargoFlowChart::class,
            PeriodSummary::class,
            ReceivedMassChart::class,
            DispatchVolumeChart::class,
            CommodityThroughputChart::class,
            CommodityBreakdownTable::class,
            DischargeAnalysisTable::class,
            ReconciliationTable::class,
        ], $dashboard->getWidgets());

        $this->assertSame([
            'default' => 1,
            'lg' => 2,
        ], $dashboard->getColumns());
    }

    public function test_dashboard_kpi_cards_stay_readable_on_tablet_widths(): void
    {
        $widget = app(TerminalOverview::class);
        $method = new \ReflectionMethod($widget, 'getColumns');
        $method->setAccessible(true);

        $this->assertSame([
            'default' => 1,
            'sm' => 2,
            'xl' => 4,
        ], $method->invoke($widget));
    }
}
