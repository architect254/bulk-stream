<?php

namespace App\Filament\Widgets;

use App\Application\Dashboard\TerminalDashboardReadModel;
use App\Filament\Support\Quantity;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TerminalOverview extends StatsOverviewWidget
{
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 10;

    /**
     * Four cards are the desktop target, but the KPI values and their
     * descriptions are long enough that four cards do not fit on a tablet.
     * Filament's default (four columns as soon as the container reaches 36rem)
     * lets the last card overflow the viewport between roughly 768 and 1279
     * CSS pixels, so stay at two columns until the panel is genuinely wide.
     *
     * @return array<string, int>
     */
    protected function getColumns(): int|array|null
    {
        return [
            'default' => 1,
            'sm' => 2,
            'xl' => 4,
        ];
    }

    protected function getStats(): array
    {
        $dashboard = app(TerminalDashboardReadModel::class);

        $received = $dashboard->todayReceived();
        $dispatched = $dashboard->todayDispatched();
        $inventory = $dashboard->inventory();

        $commodityBreakdown = collect($inventory['by_commodity'])
            ->map(fn (string $quantity, string $code): string => $code.' '.Quantity::mass($quantity))
            ->implode(' · ');

        return [
            Stat::make('Received today (MT)', Quantity::mass($received['net_received_mass']))
                ->description($received['operations'] === 0
                    ? 'No completed inbound discharges today'
                    : sprintf(
                        '%s MT gross · %s MT transit loss · %d completed discharge%s',
                        Quantity::mass($received['gross_mass']),
                        Quantity::mass($received['transit_loss_mass']),
                        $received['operations'],
                        $received['operations'] === 1 ? '' : 's',
                    ))
                ->descriptionIcon(Heroicon::OutlinedArrowDownTray)
                ->color('success'),
            Stat::make('Dispatched today (MT)', Quantity::mass($dispatched['quantity']))
                ->description($dispatched['operations'] === 0
                    ? 'No completed truck dispatches today'
                    : sprintf(
                        '%d completed truck dispatch%s',
                        $dispatched['operations'],
                        $dispatched['operations'] === 1 ? '' : 'es',
                    ))
                ->descriptionIcon(Heroicon::OutlinedTruck)
                ->color('info'),
            Stat::make('Current inventory (MT)', Quantity::mass($inventory['current_quantity']))
                ->description($commodityBreakdown)
                ->descriptionIcon(Heroicon::OutlinedCube)
                ->color('primary'),
            Stat::make('Available capacity (MT)', Quantity::mass($inventory['available_capacity']))
                ->description(sprintf(
                    'of %s MT installed across %d active tank%s',
                    Quantity::mass($inventory['capacity']),
                    $inventory['tanks'],
                    $inventory['tanks'] === 1 ? '' : 's',
                ))
                ->descriptionIcon(Heroicon::OutlinedArrowsPointingIn)
                ->color('gray'),
        ];
    }
}
