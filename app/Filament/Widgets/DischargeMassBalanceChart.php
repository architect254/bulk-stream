<?php

namespace App\Filament\Widgets;

use App\Application\Reporting\DischargeMassBalanceReadModel;
use App\Filament\Support\ChartPalette;
use App\Filament\Support\Quantity;
use App\Models\Discharge;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Locked;

class DischargeMassBalanceChart extends ChartWidget
{
    #[Locked]
    public int $dischargeId = 0;

    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 20;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Discharge mass balance (MT)';

    protected ?string $maxHeight = '120px';

    private ?array $balance = null;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function balance(): array
    {
        if ($this->balance === null) {
            if ($this->dischargeId <= 0) {
                $this->balance = [
                    'status' => 'unavailable',
                    'reason' => 'No discharge was selected.',
                    'gross_mass' => null,
                    'transit_loss_mass' => null,
                    'net_received_mass' => null,
                    'loss_basis_points' => null,
                ];
            } else {
                $discharge = Discharge::query()->find($this->dischargeId);
                if ($discharge === null) {
                    $this->balance = [
                        'status' => 'unavailable',
                        'reason' => 'The selected discharge could not be found.',
                        'gross_mass' => null,
                        'transit_loss_mass' => null,
                        'net_received_mass' => null,
                        'loss_basis_points' => null,
                    ];
                } else {
                    $this->balance = app(DischargeMassBalanceReadModel::class)->forDischarge($discharge);
                }
            }
        }

        return $this->balance;
    }

    protected function getData(): array
    {
        $balance = $this->balance();

        if ($balance['status'] !== 'available') {
            return [
                'labels' => [],
                'datasets' => [],
            ];
        }

        return [
            'labels' => ['Discharge'],
            'datasets' => [
                [
                    'label' => 'Net received (MT)',
                    'data' => [(float) $balance['net_received_mass']],
                    'backgroundColor' => ChartPalette::INBOUND_NET,
                    'maxBarThickness' => 40,
                ],
                [
                    'label' => 'Transit loss (MT)',
                    'data' => [(float) $balance['transit_loss_mass']],
                    'backgroundColor' => ChartPalette::TRANSIT_LOSS,
                    'maxBarThickness' => 40,
                ],
            ],
        ];
    }

    public function getDescription(): ?string
    {
        $balance = $this->balance();

        if ($balance['status'] === 'available') {
            return sprintf(
                'Gross %s MT = net received %s MT + transit loss %s MT (%s%%). The loss is deducted during discharge; it is not stored in the tank and is not a separate inventory movement.',
                Quantity::mass($balance['gross_mass']),
                Quantity::mass($balance['net_received_mass']),
                Quantity::mass($balance['transit_loss_mass']),
                Quantity::percent((int) $balance['loss_basis_points']),
            );
        }

        if ($balance['status'] === 'pending') {
            return 'Mass balance will be shown once the discharge is completed.';
        }

        return $balance['reason'] ?? 'Mass balance is unavailable.';
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
                                    return [];
                                }

                                const datasets = items[0].chart.data.datasets;
                                const net = Number(datasets[0]?.data?.[0] || 0);
                                const loss = Number(datasets[1]?.data?.[0] || 0);
                                const gross = net + loss;
                                const percentage = gross > 0
                                    ? (loss / gross * 100).toFixed(2)
                                    : '0.00';

                                return [
                                    'Gross: ' + mt(gross),
                                    'Transit loss: ' + percentage + '% of gross',
                                ];
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
        $balance = $this->balance();

        return $balance['status'] === 'pending'
            ? 'Mass balance pending'
            : 'Mass balance unavailable';
    }

    public function getEmptyStateDescription(): ?string
    {
        $balance = $this->balance();

        if ($balance['status'] === 'pending') {
            return 'Mass balance will be shown after the discharge completes.';
        }

        return $balance['reason'] ?? 'The persisted discharge values cannot support a mass balance.';
    }

    public function isEmpty(): bool
    {
        return $this->balance()['status'] !== 'available';
    }
}
