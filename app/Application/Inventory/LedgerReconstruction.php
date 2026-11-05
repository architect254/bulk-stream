<?php

namespace App\Application\Inventory;

use App\Domain\Cargo\Support\DecimalMath;
use App\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Single authoritative reconstruction of a tank's inventory ledger.
 *
 * Both the dashboard reconciliation table and the tank balance-progression
 * chart use this helper so that the trust decision (opening balance,
 * connectivity, arithmetic, final balance) is made in exactly one place.
 */
final class LedgerReconstruction
{
    /**
     * @param  iterable<int, InventoryMovement>  $movements
     * @return array{
     *     movements: Collection<int, InventoryMovement>,
     *     opening_balance: string,
     *     reconstructed_balance: string,
     *     stored_balance: string,
     *     difference: string,
     *     chain_valid: bool,
     *     status: 'verified'|'empty'|'discrepancy'|'unverifiable',
     *     reason: string|null,
     *     points: list<array{
     *         movement_id: int,
     *         occurred_at: mixed,
     *         balance: string,
     *         movement_type: string,
     *         quantity: string,
     *         reference: string|null
     *     }>
     * }
     */
    public function evaluate(iterable $movements, string $storedBalance): array
    {
        $sorted = collect($movements)
            ->sortBy([
                fn (InventoryMovement $left, InventoryMovement $right): int => $left->occurred_at <=> $right->occurred_at,
                fn (InventoryMovement $left, InventoryMovement $right): int => (int) $left->getKey() <=> (int) $right->getKey(),
            ])
            ->values();

        $opening = '0.000';
        $reconstructed = $opening;
        $chainValid = true;
        $reason = null;
        $points = [];

        foreach ($sorted as $movement) {
            try {
                $quantity = DecimalMath::toMinorUnits((string) $movement->quantity);
                $before = DecimalMath::toMinorUnits((string) $movement->balance_before);
                $after = DecimalMath::toMinorUnits((string) $movement->balance_after);
            } catch (Throwable) {
                $chainValid = false;
                $reason ??= 'The movement ledger contains an invalid quantity.';
                break;
            }

            $expectedBefore = DecimalMath::toMinorUnits($reconstructed);

            if ($before !== $expectedBefore) {
                $chainValid = false;
                $reason ??= $this->movementLabel($movement)
                    .' starts at '.DecimalMath::fromMinorUnits($before).' MT but the previous balance is '
                    .DecimalMath::fromMinorUnits($expectedBefore).' MT.';

                break;
            }

            $type = $movement->getAttribute('movement_type');
            $typeValue = $type instanceof InventoryMovementType ? $type->value : (string) $type;
            $direction = match ($typeValue) {
                InventoryMovementType::Inbound->value => 1,
                InventoryMovementType::Outbound->value => -1,
                default => null,
            };

            if ($direction === null) {
                $chainValid = false;
                $reason ??= 'Movement '.$this->movementLabel($movement).' has an unsupported direction.';

                break;
            }

            $reconstructed = DecimalMath::fromMinorUnits($expectedBefore + ($direction * $quantity));

            if ($after !== DecimalMath::toMinorUnits($reconstructed)) {
                $chainValid = false;
                $reason ??= $this->movementLabel($movement)
                    .' records an after-balance of '.DecimalMath::fromMinorUnits($after)
                    .' MT but before plus quantity is '.DecimalMath::fromMinorUnits(DecimalMath::toMinorUnits($reconstructed)).' MT.';

                break;
            }

            $points[] = [
                'movement_id' => (int) $movement->getKey(),
                'occurred_at' => $movement->occurred_at,
                'balance' => $reconstructed,
                'movement_type' => $typeValue === InventoryMovementType::Inbound->value ? 'Inbound' : 'Outbound',
                'quantity' => DecimalMath::fromMinorUnits($quantity),
                'reference' => $movement->reference,
            ];
        }

        $hasMovements = $sorted->isNotEmpty();

        try {
            $stored = DecimalMath::toMinorUnits($storedBalance);
            $reconstructedMinor = DecimalMath::toMinorUnits($reconstructed);
        } catch (Throwable) {
            $stored = null;
            $reconstructedMinor = null;
            $chainValid = false;
            $reason ??= 'The stored tank balance is not a valid quantity.';
        }

        $difference = $stored === null
            ? '0.000'
            : DecimalMath::fromMinorUnits($stored - (int) $reconstructedMinor);

        if (! $hasMovements) {
            $status = ($stored === 0) ? 'empty' : 'unverifiable';
            $reason ??= $status === 'empty'
                ? null
                : 'The tank holds a balance but has no recorded inventory movements.';
            $points = [];
        } elseif (! $chainValid) {
            $status = 'unverifiable';
            $points = [];
        } elseif ($stored !== null && DecimalMath::toMinorUnits($difference) === 0) {
            $status = 'verified';
        } elseif ($stored !== null) {
            $status = 'discrepancy';
            $reason ??= 'The reconstructed balance differs from the tank\'s stored balance by '.$difference.' MT.';
        } else {
            $status = 'unverifiable';
            $points = [];
        }

        return [
            'movements' => $sorted,
            'opening_balance' => $opening,
            'reconstructed_balance' => $reconstructed,
            'stored_balance' => $stored === null ? '0.000' : DecimalMath::fromMinorUnits($stored),
            'difference' => $difference,
            'chain_valid' => $chainValid && $hasMovements,
            'status' => $status,
            'reason' => $reason,
            'points' => $points,
        ];
    }

    private function movementLabel(InventoryMovement $movement): string
    {
        if (filled($movement->reference)) {
            return (string) $movement->reference;
        }

        return '#'.$movement->getKey();
    }
}
