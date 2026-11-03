<?php

namespace App\Domain\Cargo\Policies;

use App\Domain\Cargo\Exceptions\InvalidCargoQuantity;
use App\Domain\Cargo\Exceptions\TankCapacityExceeded;
use App\Domain\Cargo\Support\DecimalMath;

class TankCapacityPolicy
{
    public function assertCanReceive(
        string $currentQuantity,
        string $capacity,
        string $incomingQuantity,
        ?string $tankCode = null,
    ): void {
        $current = DecimalMath::toMinorUnits($currentQuantity);
        $limit = DecimalMath::toMinorUnits($capacity);
        $incoming = DecimalMath::toMinorUnits($incomingQuantity);

        if ($current < 0 || $limit < 0 || $incoming < 0) {
            throw new InvalidCargoQuantity('Cargo quantities cannot be negative.');
        }

        if ($incoming === 0) {
            throw new InvalidCargoQuantity('A cargo movement quantity must be greater than zero.');
        }

        if ($current + $incoming > $limit) {
            $available = DecimalMath::fromMinorUnits($limit - $current);

            throw new TankCapacityExceeded(
                tankCode: $tankCode,
                capacity: DecimalMath::fromMinorUnits($limit),
                current: DecimalMath::fromMinorUnits($current),
                incoming: DecimalMath::fromMinorUnits($incoming),
                available: $available,
            );
        }
    }
}
