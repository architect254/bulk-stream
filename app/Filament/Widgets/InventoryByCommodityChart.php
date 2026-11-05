<?php

namespace App\Filament\Widgets;

use App\Application\Dashboard\TerminalDashboardReadModel;
use App\Filament\Support\ChartPalette;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class InventoryByCommodityChart extends ChartWidget
{
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 26;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    protected ?string $heading = 'Inventory by commodity';

    protected ?string $description = 'Current inventory held in active tanks, by commodity.';

    protected ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $inventory = app(TerminalDashboardReadModel::class)->inventory();

        $labels = array_keys($inventory['by_commodity']);
        $quantities = array_map(
            static fn (string $quantity): float => (float) $quantity,
            array_values($inventory['by_commodity']),
        );

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Current inventory (MT)',
                    'data' => $quantities,
                    'backgroundColor' => ChartPalette::commodityColors($labels),
                    'borderWidth' => 1,
                    'hoverOffset' => 4,
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
            }).format(Number(value));

            return {
                cutout: '58%',
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
                            label: (context) => {
                                const value = Number(context.parsed);
                                const total = context.chart.data.datasets[0].data.reduce(
                                    (sum, item) => sum + Number(item),
                                    0,
                                );
                                const percentage = total > 0
                                    ? (value / total * 100).toFixed(2)
                                    : '0.00';

                                return context.label + ': ' + mt(value) + ' MT (' + percentage + '%)';
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
        return 'No active tank inventory';
    }

    public function getEmptyStateDescription(): ?string
    {
        return 'No active tank inventory.';
    }

    public function isEmpty(): bool
    {
        $data = $this->getCachedData()['datasets'][0]['data'] ?? [];

        return (float) array_sum(array_map('floatval', $data)) === 0.0;
    }
}
