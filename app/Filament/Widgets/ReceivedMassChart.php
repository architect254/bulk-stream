<?php

namespace App\Filament\Widgets;

use App\Application\Dashboard\OperationalAnalyticsReadModel;
use Filament\Support\RawJs;
use Filament\Widgets\BarChartWidget;

class ReceivedMassChart extends BarChartWidget
{
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Received mass by day';

    protected ?string $description = 'Completed discharge gross and net mass for the selected reporting period.';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $readModel = app(OperationalAnalyticsReadModel::class);
        [$startUtc, $endUtc] = $readModel->resolvePeriod(
            (string) request()->query('period', 'today'),
            request()->query('start_date'),
            request()->query('end_date'),
        );

        $data = $readModel->dailyReceivedMass($startUtc, $endUtc);

        return [
            'labels' => $data['labels'],
            'datasets' => [
                [
                    'label' => 'Gross received (MT)',
                    'data' => $data['gross'],
                    'backgroundColor' => '#0f766e',
                ],
                [
                    'label' => 'Net received (MT)',
                    'data' => $data['net'],
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
        return 'No completed discharges';
    }

    public function getEmptyStateDescription(): ?string
    {
        return 'No completed discharges in this period.';
    }

    public function isEmpty(): bool
    {
        $data = $this->getCachedData();
        $values = array_merge($data['datasets'][0]['data'] ?? [], $data['datasets'][1]['data'] ?? []);

        return array_sum(array_map('floatval', $values)) === 0.0;
    }
}
