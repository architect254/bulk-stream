<?php

namespace App\Filament\Widgets;

use App\Application\Dashboard\OperationalAnalyticsReadModel;
use App\Filament\Support\ChartPalette;
use App\Filament\Widgets\Concerns\UsesAnalyticsPeriod;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class ReceivedMassChart extends ChartWidget
{
    use UsesAnalyticsPeriod;

    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    protected ?string $heading = 'Received mass by day';

    protected ?string $description = 'Completed discharge gross and net mass for the selected reporting period. Transit loss is reported separately.';

    protected ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        [$startUtc, $endUtc] = $this->analyticsPeriod();

        $data = app(OperationalAnalyticsReadModel::class)->dailyReceivedMass($startUtc, $endUtc);

        return [
            'labels' => $data['labels'],
            'datasets' => [
                [
                    'label' => 'Gross received (MT)',
                    'data' => $data['gross'],
                    'backgroundColor' => ChartPalette::INBOUND,
                    'maxBarThickness' => 26,
                ],
                [
                    'label' => 'Net received (MT)',
                    'data' => $data['net'],
                    'backgroundColor' => ChartPalette::INBOUND_NET,
                    'maxBarThickness' => 26,
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
        return 'No completed discharges';
    }

    public function getEmptyStateDescription(): ?string
    {
        return 'No completed discharges in this period.';
    }

    public function isEmpty(): bool
    {
        $data = $this->getCachedData();

        $values = array_merge(
            $data['datasets'][0]['data'] ?? [],
            $data['datasets'][1]['data'] ?? [],
        );

        return (float) array_sum(array_map('floatval', $values)) === 0.0;
    }
}
