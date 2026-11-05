<?php

namespace App\Filament\Pages;

use App\Application\Dashboard\OperationalAnalyticsReadModel;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class OperationsAnalytics extends Page
{
    protected static ?string $title = 'Operations analytics';

    protected static \BackedEnum|string|null $navigationIcon = \Filament\Support\Icons\Heroicon::OutlinedChartBar;

    protected static \UnitEnum|string|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 35;

    protected string $view = 'filament.pages.operations-analytics';

    public function getHeading(): string|Htmlable|null
    {
        return 'Operations analytics';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Read-only operational summaries, commodity breakdowns, tank inventory, and ledger reconciliation.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.pages.operations-analytics')
                    ->viewData($this->getViewData()),
            ]);
    }

    public function getViewData(): array
    {
        $readModel = app(OperationalAnalyticsReadModel::class);

        $period = (string) request()->query('period', 'today');
        $startDate = request()->query('start_date');
        $endDate = request()->query('end_date');

        [$startUtc, $endUtc, $periodLabel] = $readModel->resolvePeriod($period, $startDate, $endDate);

        return [
            'period' => $period,
            'startDate' => is_string($startDate) ? $startDate : '',
            'endDate' => is_string($endDate) ? $endDate : '',
            'periodLabel' => $periodLabel,
            'startUtc' => $startUtc,
            'endUtc' => $endUtc,
            'summary' => $readModel->periodSummary($startUtc, $endUtc),
            'commodities' => $readModel->commoditySummary($startUtc, $endUtc),
            'tanks' => $readModel->tankInventoryAnalysis(),
            'discharges' => $readModel->dischargeAnalysis($startUtc, $endUtc),
            'reconciliation' => $readModel->reconciliation(),
        ];
    }
}
