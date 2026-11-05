<?php

namespace App\Filament\Widgets;

use App\Application\Dashboard\TerminalDashboardReadModel;
use App\Filament\Support\ChartPalette;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;

class CargoFlowChart extends ChartWidget
{
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 25;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    protected ?string $heading = 'Cargo flow by day';

    protected ?string $description = 'Completed inbound receipts (net mass) and outbound truck dispatches per operational day, Africa/Nairobi.';

    protected ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $flow = app(TerminalDashboardReadModel::class)->dailyCargoFlow(7);

        return [
            'labels' => $flow['labels'],
            'datasets' => [
                [
                    'label' => 'Inbound net (MT)',
                    'data' => $flow['inbound'],
                    'backgroundColor' => ChartPalette::INBOUND,
                    'maxBarThickness' => 26,
                ],
                [
                    'label' => 'Outbound (MT)',
                    'data' => $flow['outbound'],
                    'backgroundColor' => ChartPalette::OUTBOUND,
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
                        },
                    },
                },
            };
        })()
        JS);
    }

    public function getEmptyStateHeading(): string
    {
        return 'No completed cargo movements';
    }

    public function getEmptyStateDescription(): ?string
    {
        return 'No completed cargo movements in this period.';
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
