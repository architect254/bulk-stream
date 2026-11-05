<?php

namespace App\Filament\Widgets;

use App\Application\Dashboard\TerminalDashboardReadModel;
use Filament\Support\RawJs;
use Filament\Widgets\DoughnutChartWidget;

class InventoryByCommodityChart extends DoughnutChartWidget
{
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 26;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Inventory by commodity';

    protected ?string $description = 'Current inventory held in active tanks.';

    protected ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $inventory = app(TerminalDashboardReadModel::class)->inventory();

        $labels = [];
        $data = [];

        foreach ($inventory['by_commodity'] as $code => $quantity) {
            $labels[] = $code;
            $data[] = (float) $quantity;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => ['#0f766e', '#0369a1', '#b45309'],
                ],
            ],
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return RawJs::make(<<<'JS'
        {
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            var value = Number(context.parsed);
                            var total = context.chart.data.datasets[0].data.reduce(function (sum, item) { return sum + Number(item); }, 0);
                            var percentage = total > 0 ? (value / total * 100).toFixed(2) : '0.00';
                            return context.label + ': ' + value.toFixed(3) + ' MT (' + percentage + '%)';
                        },
                    },
                },
            },
        }
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

        return array_sum(array_map('floatval', $data)) === 0.0;
    }
}
