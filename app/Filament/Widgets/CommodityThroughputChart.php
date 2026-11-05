<?php

namespace App\Filament\Widgets;

use App\Application\Dashboard\OperationalAnalyticsReadModel;
use Filament\Support\RawJs;
use Filament\Widgets\BarChartWidget;

class CommodityThroughputChart extends BarChartWidget
{
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 40;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Commodity throughput and current stock';

    protected ?string $description = 'Period received net, period dispatched, and current active-tank stock.';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $readModel = app(OperationalAnalyticsReadModel::class);
        [$startUtc, $endUtc] = $readModel->resolvePeriod(
            (string) request()->query('period', 'today'),
            request()->query('start_date'),
            request()->query('end_date'),
        );

        $rows = $readModel->commoditySummary($startUtc, $endUtc);

        return [
            'labels' => $rows->pluck('code')->all(),
            'datasets' => [
                [
                    'label' => 'Received net (MT)',
                    'data' => $rows->pluck('received_net_mass')->map(fn ($value) => (float) $value)->all(),
                    'backgroundColor' => '#0f766e',
                ],
                [
                    'label' => 'Dispatched (MT)',
                    'data' => $rows->pluck('dispatched_quantity')->map(fn ($value) => (float) $value)->all(),
                    'backgroundColor' => '#475569',
                ],
                [
                    'label' => 'Current stock (MT)',
                    'data' => $rows->pluck('current_inventory')->map(fn ($value) => (float) $value)->all(),
                    'backgroundColor' => '#16a34a',
                ],
            ],
        ];
    }

    protected function getOptions(): array|RawJs|null
    {
        return RawJs::make(<<<'JS'
        {
            scales: {
                y: {
                    beginAtZero: true,
                },
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            return context.dataset.label + ': ' + Number(context.parsed.y).toFixed(3) + ' MT';
                        },
                    },
                },
            },
        }
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

        return empty($data['labels'] ?? []) || array_sum(array_map('floatval', $values)) === 0.0;
    }
}
