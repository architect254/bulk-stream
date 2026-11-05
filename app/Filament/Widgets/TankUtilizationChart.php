<?php

namespace App\Filament\Widgets;

use App\Application\Dashboard\OperationalAnalyticsReadModel;
use Filament\Support\RawJs;
use Filament\Widgets\BarChartWidget;

class TankUtilizationChart extends BarChartWidget
{
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 30;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Tank capacity utilization';

    protected ?string $description = 'Current inventory and remaining capacity for active tanks.';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $tanks = app(OperationalAnalyticsReadModel::class)
            ->tankInventoryAnalysis()
            ->where('status', 'Active')
            ->values();

        return [
            'labels' => $tanks->pluck('code')->all(),
            'datasets' => [
                [
                    'label' => 'Current (MT)',
                    'data' => $tanks->pluck('current_quantity')->map(fn ($value) => (float) $value)->all(),
                    'backgroundColor' => '#0f766e',
                ],
                [
                    'label' => 'Available (MT)',
                    'data' => $tanks->pluck('available_capacity')->map(fn ($value) => (float) $value)->all(),
                    'backgroundColor' => '#cbd5e1',
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array|RawJs|null
    {
        return RawJs::make(<<<'JS'
        {
            indexAxis: 'y',
            scales: {
                x: {
                    stacked: true,
                    beginAtZero: true,
                },
                y: {
                    stacked: true,
                },
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return context.dataset.label + ': ' + Number(context.parsed.x).toFixed(3) + ' MT';
                        },
                        afterBody: function (items) {
                            if (! items.length) {
                                return '';
                            }

                            var index = items[0].dataIndex;
                            var current = Number(items[0].chart.data.datasets[0].data[index] || 0);
                            var available = Number(items[0].chart.data.datasets[1].data[index] || 0);
                            var total = current + available;
                            var utilization = total > 0 ? (current / total * 100).toFixed(2) : '0.00';

                            return 'Utilization: ' + utilization + '%';
                        },
                    },
                },
            },
        }
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
        $data = $this->getCachedData();
        $values = array_merge($data['datasets'][0]['data'] ?? [], $data['datasets'][1]['data'] ?? []);

        return empty($data['labels'] ?? []) || array_sum(array_map('floatval', $values)) === 0.0;
    }
}
