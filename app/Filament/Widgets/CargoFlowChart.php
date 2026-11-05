<?php

namespace App\Filament\Widgets;

use App\Application\Dashboard\TerminalDashboardReadModel;
use Filament\Support\RawJs;
use Filament\Widgets\BarChartWidget;

class CargoFlowChart extends BarChartWidget
{
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 25;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Cargo flow by day';

    protected ?string $description = 'Completed inbound and outbound cargo over the last 7 operational days.';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $flow = app(TerminalDashboardReadModel::class)->dailyCargoFlow(7);

        return [
            'labels' => $flow['labels'],
            'datasets' => [
                [
                    'label' => 'Inbound (MT)',
                    'data' => $flow['inbound'],
                    'backgroundColor' => '#0f766e',
                ],
                [
                    'label' => 'Outbound (MT)',
                    'data' => $flow['outbound'],
                    'backgroundColor' => '#475569',
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
        return 'No completed cargo movements';
    }

    public function getEmptyStateDescription(): ?string
    {
        return 'No completed cargo movements in this period.';
    }

    public function isEmpty(): bool
    {
        $data = $this->getCachedData();

        return empty($data['labels'] ?? []);
    }
}
