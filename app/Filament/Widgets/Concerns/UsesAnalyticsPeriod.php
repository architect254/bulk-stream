<?php

namespace App\Filament\Widgets\Concerns;

use App\Application\Dashboard\OperationalAnalyticsReadModel;
use Carbon\CarbonImmutable;

/**
 * Carries the Operations Analytics reporting period into chart widgets.
 *
 * The page owns the period filter and passes the resolved values to each chart
 * as Livewire parameters. Widgets never read the request query themselves, so a
 * later Livewire request cannot silently fall back to a different period.
 */
trait UsesAnalyticsPeriod
{
    public string $period = 'today';

    public ?string $startDate = null;

    public ?string $endDate = null;

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function analyticsPeriod(): array
    {
        [$startUtc, $endUtc] = app(OperationalAnalyticsReadModel::class)
            ->resolvePeriod($this->period, $this->startDate, $this->endDate);

        return [$startUtc, $endUtc];
    }
}
