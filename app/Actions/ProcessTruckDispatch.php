<?php

namespace App\Actions;

use App\Domain\Cargo\Exceptions\CommodityMismatch;
use App\Domain\Cargo\Exceptions\DuplicateCargoOperation;
use App\Domain\Cargo\Exceptions\InvalidCargoState;
use App\Domain\Cargo\Policies\InventoryAvailabilityPolicy;
use App\Domain\Cargo\Policies\StateTransitionPolicy;
use App\Domain\Cargo\Support\DecimalMath;
use App\Enums\InventoryMovementType;
use App\Enums\TruckDispatchStatus;
use App\Models\InventoryMovement;
use App\Models\Tank;
use App\Models\TruckDispatch;
use Illuminate\Support\Facades\DB;

class ProcessTruckDispatch
{
    public function __construct(
        private readonly StateTransitionPolicy $statePolicy = new StateTransitionPolicy(),
        private readonly InventoryAvailabilityPolicy $availabilityPolicy = new InventoryAvailabilityPolicy(),
    ) {
    }

    public function execute(TruckDispatch|int $dispatch, ?string $idempotencyKey = null): TruckDispatch
    {
        $dispatchId = $dispatch instanceof TruckDispatch ? $dispatch->getKey() : $dispatch;

        return DB::transaction(function () use ($dispatchId, $idempotencyKey): TruckDispatch {
            $dispatch = TruckDispatch::query()
                ->with(['tank', 'commodity'])
                ->lockForUpdate()
                ->findOrFail($dispatchId);

            if ($dispatch->status === TruckDispatchStatus::Dispatched) {
                return $dispatch;
            }

            if ($dispatch->status === TruckDispatchStatus::Cancelled) {
                throw new \App\Domain\Cargo\Exceptions\InvalidStateTransition(
                    'truck dispatch',
                    $dispatch->status->value,
                    TruckDispatchStatus::Dispatched->value,
                );
            }

            if ($dispatch->status !== TruckDispatchStatus::Planned && $dispatch->status !== TruckDispatchStatus::Loading) {
                throw new InvalidCargoState('Only a planned or loading truck dispatch can be processed.');
            }

            if ($dispatch->status === TruckDispatchStatus::Planned) {
                $this->statePolicy->assertTruckDispatchTransition(TruckDispatchStatus::Planned, TruckDispatchStatus::Loading);
                $dispatch->status = TruckDispatchStatus::Loading;
                $dispatch->save();
            }

            $key = $idempotencyKey ?? $dispatch->idempotency_key ?? 'outbound-truck-dispatch:' . $dispatch->id;

            if (InventoryMovement::query()->where('idempotency_key', $key)->exists()) {
                throw new DuplicateCargoOperation();
            }

            $tank = Tank::query()
                ->lockForUpdate()
                ->findOrFail($dispatch->tank_id);

            if ($tank->commodity_id !== $dispatch->commodity_id) {
                throw new \App\Domain\Cargo\Exceptions\CommodityMismatch(
                    expected: (string) $dispatch->commodity_id,
                    actual: (string) $tank->commodity_id,
                );
            }

            $quantity = (string) $dispatch->quantity;
            $balanceBefore = (string) $tank->current_quantity;

            $this->availabilityPolicy->assertSufficient(
                $balanceBefore,
                $quantity,
                $tank->code,
            );

            $tank->current_quantity = DecimalMath::subtract($balanceBefore, $quantity);
            $tank->save();

            $occurredAt = now();

            InventoryMovement::create([
                'reference' => null,
                'tank_id' => $tank->id,
                'commodity_id' => $dispatch->commodity_id,
                'movement_type' => InventoryMovementType::Outbound,
                'quantity' => $quantity,
                'balance_before' => $balanceBefore,
                'balance_after' => $tank->current_quantity,
                'source_type' => TruckDispatch::class,
                'source_id' => $dispatch->id,
                'idempotency_key' => $key,
                'occurred_at' => $occurredAt,
            ]);

            $this->statePolicy->assertTruckDispatchTransition(TruckDispatchStatus::Loading, TruckDispatchStatus::Dispatched);

            $dispatch->status = TruckDispatchStatus::Dispatched;
            $dispatch->dispatched_at = $occurredAt;
            $dispatch->idempotency_key = $key;
            $dispatch->save();

            return $dispatch->fresh(['tank', 'commodity']);
        });
    }
}
