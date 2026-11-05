<?php

namespace App\Filament\Pages;

use App\Application\Dashboard\TerminalDashboardReadModel;
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
use App\Support\OperationalTime;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    public function getHeading(): string|Htmlable|null
    {
        return 'Bulkstream Cargo Handling System';
    }

    public function getSubheading(): string|Htmlable|null
    {
        $reportingDate = app(TerminalDashboardReadModel::class)->reportingDateLabel();

        return sprintf(
            'Mombasa Terminal · Operational overview of cargo receipts, tank storage and truck dispatches · Reporting date: %s (%s)',
            $reportingDate,
            OperationalTime::timezone(),
        );
    }

    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'lg' => 2,
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('period')
                    ->label('Reporting period')
                    ->options([
                        'today' => 'Today',
                        'last_7_days' => 'Last 7 days',
                        'current_month' => 'Current month',
                        'previous_month' => 'Previous month',
                        'custom' => 'Custom range',
                    ])
                    ->default('last_7_days')
                    ->selectablePlaceholder(false)
                    ->live(),
                DatePicker::make('startDate')
                    ->label('Start date')
                    ->default(fn (): string => OperationalTime::now()->startOfDay()->toDateString())
                    ->visible(fn (Get $get): bool => $get('period') === 'custom')
                    ->required(fn (Get $get): bool => $get('period') === 'custom')
                    ->live(),
                DatePicker::make('endDate')
                    ->label('End date')
                    ->default(fn (): string => OperationalTime::now()->toDateString())
                    ->visible(fn (Get $get): bool => $get('period') === 'custom')
                    ->required(fn (Get $get): bool => $get('period') === 'custom')
                    ->afterOrEqual('startDate')
                    ->live(),
            ]);
    }

    public function getWidgetData(): array
    {
        $period = $this->filters['period'] ?? 'last_7_days';

        if (! is_string($period) || ! in_array($period, [
            'today',
            'last_7_days',
            'current_month',
            'previous_month',
            'custom',
        ], true)) {
            $period = 'last_7_days';
        }

        $startDate = $this->filters['startDate'] ?? null;
        $endDate = $this->filters['endDate'] ?? null;

        return [
            'period' => $period,
            'startDate' => is_string($startDate) ? $startDate : null,
            'endDate' => is_string($endDate) ? $endDate : null,
        ];
    }

    public function getWidgets(): array
    {
        return [
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
        ];
    }
}
