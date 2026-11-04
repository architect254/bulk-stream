<?php

namespace App\Filament\Widgets;

use App\Application\Dashboard\TerminalDashboardReadModel;
use App\Filament\Resources\Discharges\DischargeResource;
use App\Filament\Resources\Tanks\TankResource;
use App\Filament\Resources\TruckDispatches\TruckDispatchResource;
use App\Filament\Resources\Vessels\VesselResource;
use App\Filament\Support\Quantity;
use Filament\Widgets\Widget;

class OperationalFlow extends Widget
{
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 20;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.operational-flow';

    protected function getViewData(): array
    {
        $dashboard = app(TerminalDashboardReadModel::class);

        $flow = $dashboard->flow();
        $inventory = $dashboard->inventory();
        $received = $dashboard->todayReceived();
        $dispatched = $dashboard->todayDispatched();

        return [
            'steps' => [
                [
                    'label' => 'Vessels',
                    'count' => $flow['vessels']['total'],
                    'detail' => sprintf(
                        '%d arriving · %d discharged',
                        $flow['vessels']['arriving'],
                        $flow['vessels']['discharged'],
                    ),
                    'url' => VesselResource::getUrl('index'),
                ],
                [
                    'label' => 'Inbound discharges',
                    'count' => $flow['discharges']['total'],
                    'detail' => sprintf(
                        '%d pending · %d completed today',
                        $flow['discharges']['draft'] + $flow['discharges']['processing'],
                        $received['operations'],
                    ),
                    'url' => DischargeResource::getUrl('index'),
                ],
                [
                    'label' => 'Storage tanks',
                    'count' => $flow['tanks']['active'],
                    'detail' => sprintf(
                        '%s MT stored · %s MT free',
                        Quantity::mass($inventory['current_quantity']),
                        Quantity::mass($inventory['available_capacity']),
                    ),
                    'url' => TankResource::getUrl('index'),
                ],
                [
                    'label' => 'Truck dispatches',
                    'count' => $flow['dispatches']['total'],
                    'detail' => sprintf(
                        '%d pending · %d dispatched today',
                        $flow['dispatches']['planned'] + $flow['dispatches']['loading'],
                        $dispatched['operations'],
                    ),
                    'url' => TruckDispatchResource::getUrl('index'),
                ],
            ],
        ];
    }
}
