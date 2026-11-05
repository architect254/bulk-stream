<?php

namespace App\Filament\Widgets;

use App\Application\Dashboard\OperationalAnalyticsReadModel;
use App\Filament\Support\ChartPalette;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class TankUtilizationChart extends ChartWidget
{
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 30;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Tank capacity utilization';

    protected ?string $description = 'Current inventory and remaining capacity for active tanks. Each bar is scaled to that tank\'s configured capacity.';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $tanks = app(OperationalAnalyticsReadModel::class)->activeTankUtilization();

        return [
            'labels' => $tanks->pluck('code')->all(),
            'datasets' => [
                [
                    'label' => 'Current inventory (MT)',
                    'data' => $tanks->pluck('current_quantity')->map(static fn (string $value): float => (float) $value)->all(),
                    'capacity' => $tanks->pluck('capacity')->map(static fn (string $value): float => (float) $value)->all(),
                    'backgroundColor' => ChartPalette::INBOUND,
                    'maxBarThickness' => 28,
                ],
                [
                    'label' => 'Available capacity (MT)',
                    'data' => $tanks->pluck('available_capacity')->map(static fn (string $value): float => (float) $value)->all(),
                    'backgroundColor' => ChartPalette::AVAILABLE,
                    'maxBarThickness' => 28,
                ],
            ],
        ];
    }

    protected function getMaxHeight(): ?string
    {
        $tanks = count($this->getCachedData()['labels'] ?? []);

        return min(520, max(280, 90 + ($tanks * 46))).'px';
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
                indexAxis: 'y',
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    x: {
                        stacked: true,
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'MT',
                        },
                    },
                    y: {
                        stacked: true,
                        grid: {
                            display: false,
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
                            label: (context) => context.dataset.label + ': ' + mt(context.parsed.x),
                            afterBody: (items) => {
                                if (! items.length) {
                                    return '';
                                }

                                const index = items[0].dataIndex;
                                const dataset = items[0].chart.data.datasets[0];
                                const current = Number(dataset.data[index] || 0);
                                const available = Number(items[0].chart.data.datasets[1].data[index] || 0);
                                const capacity = Number(dataset.capacity?.[index] ?? (current + available));
                                const utilization = capacity > 0
                                    ? (current / capacity * 100).toFixed(2) + '%'
                                    : 'not applicable (no capacity configured)';

                                return ['Capacity: ' + mt(capacity), 'Utilization: ' + utilization];
                            },
                        },
                    },
                },
            };
        })()
        JS);
    }

    public function getEmptyStateHeading(): string
    {
        return 'No active tanks';
    }

    public function getEmptyStateDescription(): ?string
    {
        return 'No active tanks.';
    }

    public function isEmpty(): bool
    {
        return empty($this->getCachedData()['labels'] ?? []);
    }
}
