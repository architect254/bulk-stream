<?php

namespace App\Filament\Pages;

use App\Application\Dashboard\TerminalDashboardReadModel;
use App\Filament\Widgets\OperationalExceptions;
use App\Filament\Widgets\OperationalFlow;
use App\Filament\Widgets\RecentMovements;
use App\Filament\Widgets\TankBoard;
use App\Filament\Widgets\TerminalOverview;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    public function getHeading(): string|Htmlable|null
    {
        return 'Bulkstream Cargo Handling System';
    }

    public function getSubheading(): string|Htmlable|null
    {
        $reportingDate = app(TerminalDashboardReadModel::class)->reportingDateLabel();

        return sprintf(
            'Mombasa Terminal · Operational overview of cargo receipts, tank storage and truck dispatches · Reporting date: %s (Africa/Nairobi)',
            $reportingDate,
        );
    }

    public function getColumns(): int|array
    {
        return 1;
    }

    public function getWidgets(): array
    {
        return [
            TerminalOverview::class,
            OperationalFlow::class,
            TankBoard::class,
            OperationalExceptions::class,
            RecentMovements::class,
        ];
    }
}
