<?php

namespace App\Filament\Widgets\Concerns;

use App\Application\Dashboard\OperationalAnalyticsReadModel;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Reactive;

/**
 * Carries the Operations Analytics reporting period into chart widgets.
 *
 * The page owns the period filter and passes the resolved values to each chart
 * as Livewire parameters. Widgets never read the request query themselves, so a
 * later Livewire request cannot silently fall back to a different period.
 */
trait UsesAnalyticsPeriod
{
    #[Reactive]
    public string $period = 'last_7_days';

    #[Reactive]
    public ?string $startDate = null;

    #[Reactive]
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
