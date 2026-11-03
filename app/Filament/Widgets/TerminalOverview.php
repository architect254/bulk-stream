<?php

namespace App\Filament\Widgets;

use App\Models\Discharge;
use App\Models\Tank;
use App\Models\TruckDispatch;
use Filament\Widgets\StatsOverviewWidget;
use App\Domain\Cargo\Support\DecimalMath;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TerminalOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $inventory = (string) Tank::query()->sum('current_quantity');
        $capacity = (string) Tank::query()->sum('capacity');
        $remaining = DecimalMath::subtract($capacity, $inventory);

        return [
            Stat::make('Stored inventory', number_format((float) $inventory, 3) . ' MT')
                ->description('Current quantity across all tanks')
                ->color('success'),
            Stat::make('Available capacity', number_format((float) $remaining, 3) . ' MT')
                ->description('Remaining storage capacity')
                ->color('primary'),
            Stat::make('Completed discharges', (string) Discharge::query()->where('status', 'completed')->count())
                ->description('Inbound cargo operations completed')
                ->color('info'),
            Stat::make('Completed dispatches', (string) TruckDispatch::query()->where('status', 'dispatched')->count())
                ->description('Truck deliveries completed')
                ->color('warning'),
        ];
    }
}
