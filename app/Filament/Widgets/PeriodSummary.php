<?php

namespace App\Filament\Widgets;

use App\Application\Dashboard\OperationalAnalyticsReadModel;
use App\Filament\Support\Quantity;
use App\Filament\Widgets\Concerns\UsesAnalyticsPeriod;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PeriodSummary extends StatsOverviewWidget
{
    use UsesAnalyticsPeriod;

    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 15;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Reporting period summary';

    protected function getColumns(): int|array|null
    {
        return [
            'default' => 1,
            'sm' => 2,
            'xl' => 4,
        ];
    }

    protected function getDescription(): ?string
    {
        [, , $label] = app(OperationalAnalyticsReadModel::class)
            ->resolvePeriod($this->period, $this->startDate, $this->endDate);

        return sprintf(
            '%s (Africa/Nairobi). Period figures include completed operations only; outstanding work is not limited to the period.',
            $label,
        );
    }

    protected function getStats(): array
    {
        [$startUtc, $endUtc] = $this->analyticsPeriod();

        $summary = app(OperationalAnalyticsReadModel::class)->periodSummary($startUtc, $endUtc);

        $pendingTotal = $summary['pending']['draft_discharges']
            + $summary['pending']['processing_discharges']
            + $summary['pending']['planned_dispatches']
            + $summary['pending']['loading_dispatches']
            + $summary['pending']['arriving_vessels'];

        return [
            Stat::make('Period received net (MT)', Quantity::mass($summary['received']['net_received_mass']))
                ->description($summary['received']['operations'] === 0
                    ? 'No completed discharges in the selected period'
                    : sprintf(
                        '%d completed discharge%s',
                        $summary['received']['operations'],
                        $summary['received']['operations'] === 1 ? '' : 's',
                    ))
                ->descriptionIcon(Heroicon::OutlinedArrowDownTray)
                ->color('success'),
            Stat::make('Period gross received (MT)', Quantity::mass($summary['received']['gross_mass']))
                ->description(sprintf(
                    'Transit loss %s MT · standard inbound loss 0.5%%',
                    Quantity::mass($summary['received']['transit_loss_mass']),
                ))
                ->descriptionIcon(Heroicon::OutlinedScale)
                ->color('warning'),
            Stat::make('Period dispatched (MT)', Quantity::mass($summary['dispatched']['quantity']))
                ->description($summary['dispatched']['operations'] === 0
                    ? 'No completed truck dispatches in the selected period'
                    : sprintf(
                        '%d completed dispatch%s',
                        $summary['dispatched']['operations'],
                        $summary['dispatched']['operations'] === 1 ? '' : 'es',
                    ))
                ->descriptionIcon(Heroicon::OutlinedTruck)
                ->color('info'),
            Stat::make('Outstanding work (MT)', (string) $pendingTotal)
                ->description(sprintf(
                    '%d draft/processing discharge%s · %d planned/loading dispatch%s · %d arriving vessel%s',
                    $summary['pending']['draft_discharges'] + $summary['pending']['processing_discharges'],
                    ($summary['pending']['draft_discharges'] + $summary['pending']['processing_discharges']) === 1 ? '' : 'es',
                    $summary['pending']['planned_dispatches'] + $summary['pending']['loading_dispatches'],
                    ($summary['pending']['planned_dispatches'] + $summary['pending']['loading_dispatches']) === 1 ? '' : 'es',
                    $summary['pending']['arriving_vessels'],
                    $summary['pending']['arriving_vessels'] === 1 ? '' : 's',
                ))
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color('gray'),
        ];
    }
}
