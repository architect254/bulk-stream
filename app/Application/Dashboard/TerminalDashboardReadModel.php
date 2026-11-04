<?php

namespace App\Application\Dashboard;

use App\Domain\Cargo\Support\DecimalMath;
use App\Enums\CommodityCode;
use App\Enums\DischargeStatus;
use App\Enums\TankStatus;
use App\Enums\TruckDispatchStatus;
use App\Enums\VesselStatus;
use App\Models\Discharge;
use App\Models\InventoryMovement;
use App\Models\Tank;
use App\Models\TruckDispatch;
use App\Models\Vessel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Read-only dashboard queries.
 *
 * The dashboard never writes to operational tables. All totals are derived
 * from the persisted discharge, dispatch, tank and inventory movement records.
 */
final class TerminalDashboardReadModel
{
    public const TIMEZONE = 'Africa/Nairobi';

    private ?CarbonImmutable $reportingDate = null;

    private ?array $todayReceived = null;

    private ?array $todayDispatched = null;

    private ?array $inventory = null;

    private ?array $flow = null;

    private ?array $attentionGroups = null;

    private ?Collection $recentMovements = null;

    public function reportingDate(): CarbonImmutable
    {
        return $this->reportingDate ??= CarbonImmutable::now(self::TIMEZONE);
    }

    public function reportingDateLabel(): string
    {
        return $this->reportingDate()->format('l, j F Y');
    }

    /**
     * @return array{operations: int, gross_mass: string, transit_loss_mass: string, net_received_mass: string}
     */
    public function todayReceived(): array
    {
        if ($this->todayReceived !== null) {
            return $this->todayReceived;
        }

        [$start, $end] = $this->dayWindow();

        $row = Discharge::query()
            ->where('status', DischargeStatus::Completed)
            ->whereBetween('completed_at', [$start, $end])
            ->selectRaw(
                'COUNT(*) as operations,
                COALESCE(SUM(gross_mass), 0) as gross_mass,
                COALESCE(SUM(transit_loss_mass), 0) as transit_loss_mass,
                COALESCE(SUM(net_received_mass), 0) as net_received_mass'
            )
            ->first();

        return $this->todayReceived = [
            'operations' => (int) ($row->operations ?? 0),
            'gross_mass' => $this->decimal($row->gross_mass ?? '0'),
            'transit_loss_mass' => $this->decimal($row->transit_loss_mass ?? '0'),
            'net_received_mass' => $this->decimal($row->net_received_mass ?? '0'),
        ];
    }

    /**
     * @return array{operations: int, quantity: string}
     */
    public function todayDispatched(): array
    {
        if ($this->todayDispatched !== null) {
            return $this->todayDispatched;
        }

        [$start, $end] = $this->dayWindow();

        $row = TruckDispatch::query()
            ->where('status', TruckDispatchStatus::Dispatched)
            ->whereBetween('dispatched_at', [$start, $end])
            ->selectRaw('COUNT(*) as operations, COALESCE(SUM(quantity), 0) as quantity')
            ->first();

        return $this->todayDispatched = [
            'operations' => (int) ($row->operations ?? 0),
            'quantity' => $this->decimal($row->quantity ?? '0'),
        ];
    }

    /**
     * @return array{
     *     tanks: int,
     *     current_quantity: string,
     *     capacity: string,
     *     available_capacity: string,
     *     by_commodity: array<string, string>
     * }
     */
    public function inventory(): array
    {
        if ($this->inventory !== null) {
            return $this->inventory;
        }

        $totals = Tank::query()
            ->active()
            ->selectRaw(
                'COUNT(*) as tanks,
                COALESCE(SUM(current_quantity), 0) as current_quantity,
                COALESCE(SUM(capacity), 0) as capacity'
            )
            ->first();

        $byCommodity = [];

        foreach (CommodityCode::cases() as $code) {
            $byCommodity[$code->value] = '0.000';
        }

        $commodityRows = Tank::query()
            ->active()
            ->join('commodities', 'commodities.id', '=', 'tanks.commodity_id')
            ->selectRaw('commodities.code as commodity_code, COALESCE(SUM(tanks.current_quantity), 0) as current_quantity')
            ->groupBy('commodities.code')
            ->get();

        foreach ($commodityRows as $row) {
            $code = $row->commodity_code instanceof CommodityCode ? $row->commodity_code->value : (string) $row->commodity_code;
            $byCommodity[$code] = $this->decimal($row->current_quantity);
        }

        $current = $this->decimal($totals->current_quantity ?? '0');
        $capacity = $this->decimal($totals->capacity ?? '0');

        $availableMinor = DecimalMath::toMinorUnits($capacity) - DecimalMath::toMinorUnits($current);

        return $this->inventory = [
            'tanks' => (int) ($totals->tanks ?? 0),
            'current_quantity' => $current,
            'capacity' => $capacity,
            'available_capacity' => DecimalMath::fromMinorUnits(max(0, $availableMinor)),
            'by_commodity' => $byCommodity,
        ];
    }

    /**
     * @return array{
     *     vessels: array<string, int>,
     *     discharges: array<string, int>,
     *     tanks: array<string, int>,
     *     dispatches: array<string, int>
     * }
     */
    public function flow(): array
    {
        if ($this->flow !== null) {
            return $this->flow;
        }

        $vessels = $this->statusCounts('vessels', VesselStatus::cases());
        $discharges = $this->statusCounts('discharges', DischargeStatus::cases());
        $dispatches = $this->statusCounts('truck_dispatches', TruckDispatchStatus::cases());

        $tanks = [
            'active' => Tank::query()->where('status', TankStatus::Active->value)->count(),
            'inactive' => Tank::query()->where('status', TankStatus::Inactive->value)->count(),
        ];

        return $this->flow = [
            'vessels' => [
                ...$vessels,
                'total' => array_sum($vessels),
            ],
            'discharges' => [
                ...$discharges,
                'total' => array_sum($discharges),
            ],
            'tanks' => $tanks,
            'dispatches' => [
                ...$dispatches,
                'total' => array_sum($dispatches),
            ],
        ];
    }

    /**
     * @return array<int, array{
     *     key: string,
     *     title: string,
     *     description: string,
     *     severity: string,
     *     count: int,
     *     records: Collection<int, mixed>
     * }>
     */
    public function attentionGroups(): array
    {
        if ($this->attentionGroups !== null) {
            return $this->attentionGroups;
        }

        $groups = array_filter([
            $this->attentionGroup(
                key: 'failed-discharges',
                title: 'Failed inbound discharges',
                description: 'Discharges that did not complete and need review.',
                severity: 'danger',
                query: Discharge::query()
                    ->where('status', DischargeStatus::Failed)
                    ->with(['vessel', 'tank'])
                    ->latest('updated_at'),
            ),
            $this->attentionGroup(
                key: 'processing-discharges',
                title: 'Discharges left processing',
                description: 'A discharge is marked as processing but has not completed.',
                severity: 'warning',
                query: Discharge::query()
                    ->where('status', DischargeStatus::Processing)
                    ->with(['vessel', 'tank'])
                    ->latest('updated_at'),
            ),
            $this->attentionGroup(
                key: 'tanks-over-capacity',
                title: 'Tanks above configured capacity',
                description: 'Stored inventory exceeds the tank capacity and needs investigation.',
                severity: 'danger',
                query: Tank::query()
                    ->whereColumn('current_quantity', '>', 'capacity')
                    ->with('commodity')
                    ->orderBy('code'),
            ),
            $this->attentionGroup(
                key: 'negative-tank-balances',
                title: 'Tanks with negative inventory',
                description: 'A negative stored balance is not a valid terminal state.',
                severity: 'danger',
                query: Tank::query()
                    ->where('current_quantity', '<', 0)
                    ->with('commodity')
                    ->orderBy('code'),
            ),
            $this->attentionGroup(
                key: 'inactive-tanks-with-inventory',
                title: 'Inactive tanks still holding cargo',
                description: 'The tank is not available for operations but still contains inventory.',
                severity: 'warning',
                query: Tank::query()
                    ->where('status', TankStatus::Inactive->value)
                    ->where('current_quantity', '>', 0)
                    ->with('commodity')
                    ->orderBy('code'),
            ),
            $this->attentionGroup(
                key: 'loading-dispatches',
                title: 'Truck dispatches left loading',
                description: 'A dispatch is marked as loading but has not been completed.',
                severity: 'warning',
                query: TruckDispatch::query()
                    ->where('status', TruckDispatchStatus::Loading)
                    ->with(['tank', 'commodity'])
                    ->latest('updated_at'),
            ),
            $this->attentionGroup(
                key: 'draft-discharges',
                title: 'Draft discharges awaiting processing',
                description: 'Inbound cargo records that are ready to be posted to a tank.',
                severity: 'info',
                query: Discharge::query()
                    ->where('status', DischargeStatus::Draft)
                    ->with(['vessel', 'tank'])
                    ->latest('created_at'),
            ),
            $this->attentionGroup(
                key: 'planned-dispatches',
                title: 'Planned truck dispatches awaiting dispatch',
                description: 'Outbound truck movements that have not been completed.',
                severity: 'info',
                query: TruckDispatch::query()
                    ->where('status', TruckDispatchStatus::Planned)
                    ->with(['tank', 'commodity'])
                    ->latest('created_at'),
            ),
            $this->attentionGroup(
                key: 'arriving-vessels',
                title: 'Vessels awaiting discharge',
                description: 'Vessels that have arrived and are ready for an inbound discharge.',
                severity: 'info',
                query: Vessel::query()
                    ->where('status', VesselStatus::Arriving)
                    ->latest('updated_at'),
            ),
        ]);

        return $this->attentionGroups = array_values($groups);
    }

    public function attentionCount(): int
    {
        return array_sum(array_column($this->attentionGroups(), 'count'));
    }

    public function recentMovements(int $limit = 10): Collection
    {
        return $this->recentMovements ??= InventoryMovement::query()
            ->with(['tank', 'commodity', 'source'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function dayWindow(): array
    {
        $day = $this->reportingDate();

        return [
            $day->startOfDay()->utc(),
            $day->endOfDay()->utc(),
        ];
    }

    /**
     * @param  array<int, \BackedEnum>  $cases
     * @return array<string, int>
     */
    private function statusCounts(string $table, array $cases): array
    {
        $counts = DB::table($table)
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $result = [];

        foreach ($cases as $case) {
            $result[$case->value] = (int) ($counts[$case->value] ?? 0);
        }

        return $result;
    }

    private function attentionGroup(
        string $key,
        string $title,
        string $description,
        string $severity,
        Builder $query,
        int $limit = 4,
    ): ?array {
        $count = (clone $query)->count();

        if ($count === 0) {
            return null;
        }

        return [
            'key' => $key,
            'title' => $title,
            'description' => $description,
            'severity' => $severity,
            'count' => $count,
            'records' => (clone $query)->limit($limit)->get(),
        ];
    }

    private function decimal(string|int|float|null $value): string
    {
        try {
            return DecimalMath::fromMinorUnits(DecimalMath::toMinorUnits((string) ($value ?? '0')));
        } catch (Throwable) {
            return '0.000';
        }
    }
}
