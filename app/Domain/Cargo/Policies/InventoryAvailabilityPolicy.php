<?php

namespace App\Domain\Cargo\Policies;

use App\Domain\Cargo\Exceptions\InsufficientTankInventory;
use App\Domain\Cargo\Exceptions\InvalidCargoQuantity;
use App\Domain\Cargo\Support\DecimalMath;

class InventoryAvailabilityPolicy
{
    public function assertSufficient(
        string $currentQuantity,
        string $requestedQuantity,
        ?string $tankCode = null,
    ): void {
        $current = DecimalMath::toMinorUnits($currentQuantity);
        $requested = DecimalMath::toMinorUnits($requestedQuantity);

        if ($current < 0 || $requested < 0) {
            throw new InvalidCargoQuantity('Cargo quantities cannot be negative.');
        }

        if ($requested === 0) {
            throw new InvalidCargoQuantity('A cargo movement quantity must be greater than zero.');
        }

        if ($requested > $current) {
            throw new InsufficientTankInventory(
                tankCode: $tankCode,
                available: DecimalMath::fromMinorUnits($current),
                requested: DecimalMath::fromMinorUnits($requested),
            );
        }
    }
}
