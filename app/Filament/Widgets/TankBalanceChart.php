<?php

namespace App\Filament\Widgets;

use App\Application\Reporting\TankBalanceHistoryReadModel;
use App\Filament\Support\ChartPalette;
use App\Filament\Support\Quantity;
use App\Models\Tank;
use App\Support\OperationalTime;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Locked;

class TankBalanceChart extends ChartWidget
{
    #[Locked]
    public int $tankId = 0;

    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 20;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Inventory balance progression (MT)';

    protected ?string $maxHeight = '320px';

    private ?array $history = null;

    protected function getType(): string
    {
        return 'line';
    }

    protected function history(): array
    {
        if ($this->history === null) {
            if ($this->tankId <= 0) {
                $this->history = [
                    'status' => 'unverifiable',
                    'reason' => 'No tank was selected.',
                    'opening_balance' => '0.000',
                    'final_balance' => '0.000',
                    'stored_balance' => '0.000',
                    'difference' => '0.000',
                    'movement_count' => 0,
                    'points' => [],
                    'tank_code' => '',
                ];
            } else {
                $tank = Tank::query()->with('commodity')->find($this->tankId);
                if ($tank === null) {
                    $this->history = [
                        'status' => 'unverifiable',
                        'reason' => 'The selected tank could not be found.',
                        'opening_balance' => '0.000',
                        'final_balance' => '0.000',
                        'stored_balance' => '0.000',
                        'difference' => '0.000',
                        'movement_count' => 0,
                        'points' => [],
                        'tank_code' => '',
                    ];
                } else {
                    $this->history = app(TankBalanceHistoryReadModel::class)->forTank($tank);
                }
            }
        }

        return $this->history;
    }

    protected function getData(): array
    {
        $history = $this->history();

        if ($history['status'] !== 'verified') {
            return [
                'labels' => [],
                'datasets' => [],
            ];
        }

        $labels = ['Opening balance'];
        $balances = [(float) $history['opening_balance']];
        $movements = [
            ['label' => 'Opening balance', 'reference' => 'No preceding movement'],
        ];

        foreach ($history['points'] as $point) {
            $local = OperationalTime::toLocal($point['occurred_at']);
            $labels[] = $local?->format('j M H:i') ?? '—';
            $balances[] = (float) $point['balance'];
            $movements[] = [
                'label' => $point['movement_type'].' '.Quantity::mass($point['quantity']).' MT',
                'reference' => (string) ($point['reference'] ?: '—'),
            ];
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Recorded balance (MT)',
                    'data' => $balances,
                    'borderColor' => ChartPalette::STOCK,
                    'backgroundColor' => ChartPalette::STOCK,
                    'borderWidth' => 2,
                    'pointRadius' => 3,
                    'pointHoverRadius' => 5,
                    'stepped' => true,
                    'fill' => false,
                    'tension' => 0,
                    'movements' => $movements,
                ],
            ],
        ];
    }

    public function getDescription(): ?string
    {
        $history = $this->history();

        return match ($history['status']) {
            'verified' => sprintf(
                'Balance recorded after each inventory movement for %s. Opening balance 0.000 MT; final balance %s MT reconciles to current stock.',
                $history['tank_code'] ?: 'this tank',
                Quantity::mass($history['final_balance']),
            ),
            'empty' => sprintf(
                'No inventory movements have been recorded for %s yet.',
                $history['tank_code'] ?: 'this tank',
            ),
            default => sprintf(
                'The recorded movement history for %s cannot currently be verified against current stock.',
                $history['tank_code'] ?: 'this tank',
            ),
        };
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
                        ticks: {
                            autoSkip: true,
                            maxRotation: 0,
                            maxTicksLimit: 8,
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
                        display: false,
                    },
                    tooltip: {
                        callbacks: {
                            label: (context) => 'Balance: ' + mt(context.parsed.y),
                            afterBody: (items) => {
                                if (! items.length) {
                                    return [];
                                }

                                const meta = items[0].dataset.movements?.[items[0].dataIndex];

                                if (! meta) {
                                    return [];
                                }

                                return [meta.label, meta.reference].filter((value) => value && value !== '—');
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
        $history = $this->history();

        return match ($history['status']) {
            'empty' => 'No inventory movement history',
            'unverifiable' => 'Balance history cannot be verified',
            default => 'No inventory balance data',
        };
    }

    public function getEmptyStateDescription(): ?string
    {
        $history = $this->history();

        if ($history['status'] === 'empty') {
            return 'No inventory movements have been recorded for this tank.';
        }

        if ($history['status'] === 'unverifiable') {
            return $history['reason'] ?? 'The recorded movement history could not be verified against current stock.';
        }

        return null;
    }

    public function isEmpty(): bool
    {
        return $this->history()['status'] !== 'verified';
    }
}
