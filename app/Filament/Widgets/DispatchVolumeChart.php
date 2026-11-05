<?php

namespace App\Filament\Widgets;

use App\Application\Dashboard\OperationalAnalyticsReadModel;
use Filament\Support\RawJs;
use Filament\Widgets\LineChartWidget;

class DispatchVolumeChart extends LineChartWidget
{
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 20;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Dispatch volume by day';

    protected ?string $description = 'Completed truck dispatch quantity for the selected reporting period.';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $readModel = app(OperationalAnalyticsReadModel::class);
        [$startUtc, $endUtc] = $readModel->resolvePeriod(
            (string) request()->query('period', 'today'),
            request()->query('start_date'),
            request()->query('end_date'),
        );

        $data = $readModel->dailyDispatchedMass($startUtc, $endUtc);

        return [
            'labels' => $data['labels'],
            'datasets' => [
                [
                    'label' => 'Dispatched (MT)',
                    'data' => $data['quantity'],
                    'borderColor' => '#475569',
                    'backgroundColor' => '#475569',
                    'fill' => false,
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
        return 'No completed dispatches';
    }

    public function getEmptyStateDescription(): ?string
    {
        return 'No completed dispatches in this period.';
    }

    public function isEmpty(): bool
    {
        $data = $this->getCachedData()['datasets'][0]['data'] ?? [];

        return array_sum(array_map('floatval', $data)) === 0.0;
    }
}
