<?php

namespace App\Application\Dashboard;

use App\Domain\Cargo\Support\DecimalMath;
use App\Enums\DischargeStatus;
use App\Enums\TankStatus;
use App\Enums\TruckDispatchStatus;
use App\Enums\VesselStatus;
use App\Models\Commodity;
use App\Models\Discharge;
use App\Models\Tank;
use App\Models\TruckDispatch;
use App\Models\Vessel;
use App\Support\OperationalTime;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class OperationalAnalyticsReadModel
{
    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}
     */
    public function resolvePeriod(string $preset, ?string $startDate = null, ?string $endDate = null): array
    {
        $now = OperationalTime::now();
        $timezone = OperationalTime::timezone();

        try {
            [$startNairobi, $endNairobi] = match ($preset) {
                'last_7_days' => [$now->subDays(6)->startOfDay(), $now->endOfDay()],
                'current_month' => [$now->startOfMonth(), $now->endOfMonth()],
                'previous_month' => [
                    $now->subMonthNoOverflow()->startOfMonth(),
                    $now->subMonthNoOverflow()->endOfMonth(),
                ],
                'custom' => $this->customPeriod($startDate, $endDate, $now),
                default => [$now->startOfDay(), $now->endOfDay()],
            };
        } catch (\Throwable) {
            $startNairobi = $now->startOfDay();
            $endNairobi = $now->endOfDay();
        }

        return [
            $startNairobi->setTimezone('UTC'),
            $endNairobi->setTimezone('UTC'),
            $startNairobi->format('j M Y').' to '.$endNairobi->format('j M Y'),
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function customPeriod(?string $startDate, ?string $endDate, CarbonImmutable $now): array
    {
        $start = $startDate !== null && $startDate !== ''
            ? CarbonImmutable::parse($startDate, OperationalTime::timezone())->startOfDay()
            : $now->startOfDay();

        $end = $endDate !== null && $endDate !== ''
            ? CarbonImmutable::parse($endDate, OperationalTime::timezone())->endOfDay()
            : $now->endOfDay();

        if ($end->lessThan($start)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }

        return [$start, $end];
    }

    /**
     * @return array<string, mixed>
     */
    public function periodSummary(CarbonImmutable $startUtc, CarbonImmutable $endUtc): array
    {
        $received = Discharge::query()
            ->where('status', DischargeStatus::Completed)
            ->whereBetween('completed_at', [$startUtc, $endUtc])
            ->selectRaw(
                'COUNT(*) as operations,
                COALESCE(SUM(gross_mass), 0) as gross_mass,
                COALESCE(SUM(transit_loss_mass), 0) as transit_loss_mass,
                COALESCE(SUM(net_received_mass), 0) as net_received_mass'
            )
            ->first();

        $dispatched = TruckDispatch::query()
            ->where('status', TruckDispatchStatus::Dispatched)
            ->whereBetween('dispatched_at', [$startUtc, $endUtc])
            ->selectRaw('COUNT(*) as operations, COALESCE(SUM(quantity), 0) as quantity')
            ->first();

        return [
            'received' => [
                'operations' => (int) ($received->operations ?? 0),
                'gross_mass' => $this->decimal($received->gross_mass ?? '0'),
                'transit_loss_mass' => $this->decimal($received->transit_loss_mass ?? '0'),
                'net_received_mass' => $this->decimal($received->net_received_mass ?? '0'),
            ],
            'dispatched' => [
                'operations' => (int) ($dispatched->operations ?? 0),
                'quantity' => $this->decimal($dispatched->quantity ?? '0'),
            ],
            'pending' => [
                'draft_discharges' => Discharge::query()->where('status', DischargeStatus::Draft)->count(),
                'processing_discharges' => Discharge::query()->where('status', DischargeStatus::Processing)->count(),
                'planned_dispatches' => TruckDispatch::query()->where('status', TruckDispatchStatus::Planned)->count(),
                'loading_dispatches' => TruckDispatch::query()->where('status', TruckDispatchStatus::Loading)->count(),
                'arriving_vessels' => Vessel::query()->where('status', VesselStatus::Arriving)->count(),
            ],
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function commoditySummary(CarbonImmutable $startUtc, CarbonImmutable $endUtc): Collection
    {
        $rows = [];

        foreach (Commodity::query()->orderBy('code')->get() as $commodity) {
            $rows[$commodity->code->value] = [
                'code' => $commodity->code->value,
                'name' => $commodity->name,
                'received_operations' => 0,
                'received_gross_mass' => '0.000',
                'received_net_mass' => '0.000',
                'transit_loss_mass' => '0.000',
                'dispatched_operations' => 0,
                'dispatched_quantity' => '0.000',
                'current_inventory' => '0.000',
                'tanks' => 0,
            ];
        }

        $received = Discharge::query()
            ->where('status', DischargeStatus::Completed)
            ->whereBetween('completed_at', [$startUtc, $endUtc])
            ->selectRaw(
                'commodity_id,
                COUNT(*) as operations,
                COALESCE(SUM(gross_mass), 0) as gross_mass,
                COALESCE(SUM(transit_loss_mass), 0) as transit_loss_mass,
                COALESCE(SUM(net_received_mass), 0) as net_received_mass'
            )
            ->groupBy('commodity_id')
            ->get();

        foreach ($received as $row) {
            $commodity = Commodity::find($row->commodity_id);

            if ($commodity === null || ! isset($rows[$commodity->code->value])) {
                continue;
            }

            $rows[$commodity->code->value]['received_operations'] = (int) $row->operations;
            $rows[$commodity->code->value]['received_gross_mass'] = $this->decimal($row->gross_mass);
            $rows[$commodity->code->value]['transit_loss_mass'] = $this->decimal($row->transit_loss_mass);
            $rows[$commodity->code->value]['received_net_mass'] = $this->decimal($row->net_received_mass);
        }

        $dispatched = TruckDispatch::query()
            ->where('status', TruckDispatchStatus::Dispatched)
            ->whereBetween('dispatched_at', [$startUtc, $endUtc])
            ->selectRaw('commodity_id, COUNT(*) as operations, COALESCE(SUM(quantity), 0) as quantity')
            ->groupBy('commodity_id')
            ->get();

        foreach ($dispatched as $row) {
            $commodity = Commodity::find($row->commodity_id);

            if ($commodity === null || ! isset($rows[$commodity->code->value])) {
                continue;
            }

            $rows[$commodity->code->value]['dispatched_operations'] = (int) $row->operations;
            $rows[$commodity->code->value]['dispatched_quantity'] = $this->decimal($row->quantity);
        }

        $inventory = Tank::query()
            ->where('status', TankStatus::Active)
            ->selectRaw('commodity_id, COUNT(*) as tanks, COALESCE(SUM(current_quantity), 0) as current_quantity')
            ->groupBy('commodity_id')
            ->get();

        foreach ($inventory as $row) {
            $commodity = Commodity::find($row->commodity_id);

            if ($commodity === null || ! isset($rows[$commodity->code->value])) {
                continue;
            }

            $rows[$commodity->code->value]['tanks'] = (int) $row->tanks;
            $rows[$commodity->code->value]['current_inventory'] = $this->decimal($row->current_quantity);
        }

        return collect(array_values($rows));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function tankInventoryAnalysis(): Collection
    {
        return Tank::query()
            ->with('commodity')
            ->orderBy('code')
            ->get()
            ->map(function (Tank $tank): array {
                $current = (string) $tank->current_quantity;
                $capacity = (string) $tank->capacity;
                $availableMinor = DecimalMath::toMinorUnits($capacity) - DecimalMath::toMinorUnits($current);

                return [
                    'code' => $tank->code,
                    'commodity' => $tank->commodity?->code?->value ?? '—',
                    'status' => $tank->status->getLabel(),
                    'capacity' => $this->decimal($capacity),
                    'current_quantity' => $this->decimal($current),
                    'available_capacity' => DecimalMath::fromMinorUnits(max(0, $availableMinor)),
                    'utilization' => DecimalMath::toMinorUnits($capacity) > 0
                        ? number_format(DecimalMath::toMinorUnits($current) * 100 / DecimalMath::toMinorUnits($capacity), 2)
                        : '0.00',
                    'movement_count' => $tank->inventoryMovements()->count(),
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function reconciliation(): Collection
    {
        return Tank::query()
            ->with('commodity')
            ->orderBy('code')
            ->get()
            ->map(function (Tank $tank): array {
                $movements = $tank->inventoryMovements()->orderBy('occurred_at')->orderBy('id')->get();
                $reconstructed = '0.000';

                foreach ($movements as $movement) {
                    if ($movement->movement_type->value === 'inbound') {
                        $reconstructed = DecimalMath::add($reconstructed, (string) $movement->quantity);
                    } else {
                        $reconstructed = DecimalMath::subtract($reconstructed, (string) $movement->quantity);
                    }
                }

                $stored = (string) $tank->current_quantity;
                $difference = DecimalMath::subtract($stored, $reconstructed);

                return [
                    'code' => $tank->code,
                    'commodity' => $tank->commodity?->code?->value ?? '—',
                    'stored_balance' => $this->decimal($stored),
                    'reconstructed_balance' => $this->decimal($reconstructed),
                    'difference' => $this->decimal($difference),
                    'movement_count' => $movements->count(),
                    'status' => DecimalMath::toMinorUnits($difference) === 0 ? 'Reconciled' : 'Difference',
                ];
            });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function dischargeAnalysis(CarbonImmutable $startUtc, CarbonImmutable $endUtc, int $limit = 100): Collection
    {
        return Discharge::query()
            ->where('status', DischargeStatus::Completed)
            ->whereBetween('completed_at', [$startUtc, $endUtc])
            ->with(['vessel', 'tank', 'commodity', 'inventoryMovements'])
            ->withCount('logs')
            ->orderByDesc('completed_at')
            ->limit($limit)
            ->get()
            ->map(function (Discharge $discharge): array {
                $movement = $discharge->inventoryMovements->first();

                return [
                    'reference' => $discharge->reference,
                    'vessel' => $discharge->vessel?->name ?? '—',
                    'tank' => $discharge->tank?->code ?? '—',
                    'commodity' => $discharge->commodity?->code?->value ?? '—',
                    'gross_mass' => $this->decimal((string) $discharge->gross_mass),
                    'transit_loss_mass' => $this->decimal((string) $discharge->transit_loss_mass),
                    'net_received_mass' => $this->decimal((string) $discharge->net_received_mass),
                    'loss_percentage' => DecimalMath::toMinorUnits((string) $discharge->gross_mass) > 0
                        ? number_format(DecimalMath::toMinorUnits((string) $discharge->transit_loss_mass) * 100 / DecimalMath::toMinorUnits((string) $discharge->gross_mass), 2)
                        : '0.00',
                    'source_log_count' => (int) $discharge->logs_count,
                    'started_at' => $discharge->started_at,
                    'completed_at' => $discharge->completed_at,
                    'resulting_movement' => $movement?->reference ?? '—',
                ];
            });
    }

    private function decimal(string|int|float|null $value): string
    {
        try {
            return DecimalMath::fromMinorUnits(DecimalMath::toMinorUnits((string) ($value ?? '0')));
        } catch (\Throwable) {
            return '0.000';
        }
    }
}
