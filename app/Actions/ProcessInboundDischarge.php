<?php

namespace App\Actions;

use App\Domain\Cargo\Exceptions\CommodityMismatch;
use App\Domain\Cargo\Exceptions\DuplicateCargoOperation;
use App\Domain\Cargo\Exceptions\InvalidCargoQuantity;
use App\Domain\Cargo\Exceptions\InvalidCargoState;
use App\Domain\Cargo\Policies\ChronologyPolicy;
use App\Domain\Cargo\Policies\StateTransitionPolicy;
use App\Domain\Cargo\Policies\TankCapacityPolicy;
use App\Domain\Cargo\Services\ChronologicalLogSorter;
use App\Domain\Cargo\Services\TransitLossCalculator;
use App\Domain\Cargo\Support\DecimalMath;
use App\Domain\Cargo\ValueObjects\DischargeLogEntry;
use App\Enums\DischargeStatus;
use App\Enums\InventoryMovementType;
use App\Enums\VesselStatus;
use App\Models\Discharge;
use App\Models\InventoryMovement;
use App\Models\Tank;
use App\Models\Vessel;
use Illuminate\Support\Facades\DB;

class ProcessInboundDischarge
{
    public function __construct(
        private readonly TransitLossCalculator $transitLossCalculator = new TransitLossCalculator(),
        private readonly ChronologicalLogSorter $logSorter = new ChronologicalLogSorter(),
        private readonly StateTransitionPolicy $statePolicy = new StateTransitionPolicy(),
        private readonly TankCapacityPolicy $capacityPolicy = new TankCapacityPolicy(),
        private readonly ChronologyPolicy $chronologyPolicy = new ChronologyPolicy(),
    ) {
    }

    public function execute(Discharge|int $discharge, ?string $idempotencyKey = null): Discharge
    {
        $dischargeId = $discharge instanceof Discharge ? $discharge->getKey() : $discharge;

        return DB::transaction(function () use ($dischargeId, $idempotencyKey): Discharge {
            $discharge = Discharge::query()
                ->with(['logs', 'tank', 'commodity', 'vessel'])
                ->lockForUpdate()
                ->findOrFail($dischargeId);

            if ($discharge->status === DischargeStatus::Completed) {
                return $discharge;
            }

            if ($discharge->status !== DischargeStatus::Draft) {
                throw new InvalidCargoState('Only a draft discharge can be processed.');
            }

            $this->statePolicy->assertDischargeTransition(DischargeStatus::Draft, DischargeStatus::Processing);

            $discharge->load('logs');

            if ($discharge->logs->isEmpty()) {
                throw new InvalidCargoState('Discharge has no source logs.');
            }

            if ($discharge->tank->commodity_id !== $discharge->commodity_id) {
                throw new \App\Domain\Cargo\Exceptions\CommodityMismatch(
                    expected: (string) $discharge->commodity_id,
                    actual: (string) $discharge->tank->commodity_id,
                );
            }

            $vessel = Vessel::query()
                ->lockForUpdate()
                ->findOrFail($discharge->vessel_id);

            $this->statePolicy->assertVesselTransition($vessel->status, VesselStatus::Discharged);

            $entries = $discharge->logs->map(
                fn ($log) => new DischargeLogEntry(
                    occurredAt: $log->occurred_at,
                    sequence: $log->sequence,
                    id: $log->id,
                    externalEventId: $log->external_event_id,
                )
            );

            $sorted = $this->logSorter->sort($entries);

            $gross = '0.000';

            foreach ($sorted as $index => $entry) {
                $log = $discharge->logs->firstWhere('id', $entry->id);

                if ($log === null) {
                    throw new InvalidCargoState('Source log could not be resolved during processing.');
                }

                $logGross = (string) $log->gross_mass;

                if (DecimalMath::toMinorUnits($logGross) <= 0) {
                    throw new InvalidCargoQuantity('Source log gross mass must be greater than zero.');
                }

                $gross = DecimalMath::add($gross, $logGross);
            }

            $massBalance = $this->transitLossCalculator->calculate($gross);

            $key = $idempotencyKey ?? $discharge->idempotency_key ?? 'inbound-discharge:' . $discharge->id;

            if (InventoryMovement::query()->where('idempotency_key', $key)->exists()) {
                throw new \App\Domain\Cargo\Exceptions\DuplicateCargoOperation();
            }

            $tank = Tank::query()
                ->lockForUpdate()
                ->findOrFail($discharge->tank_id);

            $this->capacityPolicy->assertCanReceive(
                (string) $tank->current_quantity,
                (string) $tank->capacity,
                $massBalance->net,
                $tank->code,
            );

            $balanceBefore = (string) $tank->current_quantity;
            $tank->current_quantity = DecimalMath::add($balanceBefore, $massBalance->net);
            $tank->save();

            $vessel->status = VesselStatus::Discharged;
            $vessel->save();

            $lastOccurredAt = $sorted[count($sorted) - 1]->occurredAt;

            InventoryMovement::create([
                'reference' => null,
                'tank_id' => $tank->id,
                'commodity_id' => $discharge->commodity_id,
                'movement_type' => InventoryMovementType::Inbound,
                'quantity' => $massBalance->net,
                'balance_before' => $balanceBefore,
                'balance_after' => $tank->current_quantity,
                'source_type' => Discharge::class,
                'source_id' => $discharge->id,
                'idempotency_key' => $key,
                'occurred_at' => $lastOccurredAt,
            ]);

            foreach ($discharge->logs as $log) {
                $log->processed_at = now();
                $log->save();
            }

            $discharge->status = DischargeStatus::Processing;
            $discharge->started_at = $discharge->started_at ?? $sorted[0]->occurredAt;
            $discharge->gross_mass = $massBalance->gross;
            $discharge->transit_loss_mass = $massBalance->loss;
            $discharge->net_received_mass = $massBalance->net;
            $discharge->save();

            $this->statePolicy->assertDischargeTransition(DischargeStatus::Processing, DischargeStatus::Completed);

            $discharge->status = DischargeStatus::Completed;
            $discharge->completed_at = now();
            $discharge->idempotency_key = $key;
            $discharge->save();

            return $discharge->fresh(['logs', 'tank', 'commodity', 'vessel']);
        });
    }
}
