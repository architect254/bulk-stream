<?php

namespace App\Filament\Widgets;

use App\Application\Dashboard\OperationalAnalyticsReadModel;
use App\Filament\Support\ChartPalette;
use App\Filament\Widgets\Concerns\UsesAnalyticsPeriod;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class CommodityThroughputChart extends ChartWidget
{
    use UsesAnalyticsPeriod;

    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 40;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Commodity throughput and current stock';

    protected ?string $description = 'Selected-period received net and dispatched mass compared with current active-tank inventory.';

    protected ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        [$startUtc, $endUtc] = $this->analyticsPeriod();

        $rows = app(OperationalAnalyticsReadModel::class)->commoditySummary($startUtc, $endUtc);

        return [
            'labels' => $rows->pluck('code')->all(),
            'datasets' => [
                [
                    'label' => 'Period received net (MT)',
                    'data' => $rows->pluck('received_net_mass')->map(static fn (string $value): float => (float) $value)->all(),
                    'backgroundColor' => ChartPalette::INBOUND,
                    'maxBarThickness' => 26,
                ],
                [
                    'label' => 'Period dispatched (MT)',
                    'data' => $rows->pluck('dispatched_quantity')->map(static fn (string $value): float => (float) $value)->all(),
                    'backgroundColor' => ChartPalette::OUTBOUND,
                    'maxBarThickness' => 26,
                ],
                [
                    'label' => 'Current stock (MT)',
                    'data' => $rows->pluck('current_inventory')->map(static fn (string $value): float => (float) $value)->all(),
                    'backgroundColor' => ChartPalette::STOCK,
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
                            afterBody: () => 'Current stock is measured now, not at the end of the period.',
                        },
                    },
                },
            };
        })()
        JS);
    }

    public function getEmptyStateHeading(): string
    {
        return 'No commodity data';
    }

    public function getEmptyStateDescription(): ?string
    {
        return 'No commodity data available for this period.';
    }

    public function isEmpty(): bool
    {
        $data = $this->getCachedData();

        $values = array_merge(
            $data['datasets'][0]['data'] ?? [],
            $data['datasets'][1]['data'] ?? [],
            $data['datasets'][2]['data'] ?? [],
        );

        return (float) array_sum(array_map('floatval', $values)) === 0.0;
    }
}
