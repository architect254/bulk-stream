<?php

namespace App\Filament\Widgets;

use App\Application\Dashboard\OperationalAnalyticsReadModel;
use App\Filament\Support\ChartPalette;
use App\Filament\Widgets\Concerns\UsesAnalyticsPeriod;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class DispatchVolumeChart extends ChartWidget
{
    use UsesAnalyticsPeriod;

    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 20;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    protected ?string $heading = 'Dispatch volume by day';

    protected ?string $description = 'Completed truck dispatch quantity for the selected reporting period.';

    protected ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        [$startUtc, $endUtc] = $this->analyticsPeriod();

        $data = app(OperationalAnalyticsReadModel::class)->dailyDispatchedMass($startUtc, $endUtc);

        return [
            'labels' => $data['labels'],
            'datasets' => [
                [
                    'label' => 'Dispatched (MT)',
                    'data' => $data['quantity'],
                    'borderColor' => ChartPalette::OUTBOUND,
                    'backgroundColor' => ChartPalette::OUTBOUND,
                    'borderWidth' => 2,
                    'pointRadius' => 3,
                    'pointHoverRadius' => 4,
                    'fill' => false,
                ],
            ],
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return RawJs::make(<<<'JS'
        (() => {
            const mt = (value) => new Intl.NumberFormat('en-KE', {
                minimumFractionDigits: 3,
                maximumFractionDigits: 3,
            }).format(Number(value)) + ' MT';

            return {
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: {
                        grid: {
                            display: false,
                        },
                        ticks: {
                            autoSkip: true,
                            maxRotation: 0,
                            maxTicksLimit: 12,
                        },
                    },
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'MT',
                        },
                    },
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 9,
                            boxHeight: 9,
                        },
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => context.dataset.label + ': ' + mt(context.parsed.y),
                        },
                    },
                },
            };
        })()
        JS);
    }

    public function getEmptyStateHeading(): string
    {
        return 'No completed dispatches';
    }

    public function getEmptyStateDescription(): ?string
    {
        return 'No completed dispatches in this period.';
    }

    public function isEmpty(): bool
    {
        $data = $this->getCachedData()['datasets'][0]['data'] ?? [];

        return (float) array_sum(array_map('floatval', $data)) === 0.0;
    }
}
