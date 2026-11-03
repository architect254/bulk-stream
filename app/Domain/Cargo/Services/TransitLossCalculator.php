<?php

namespace App\Domain\Cargo\Services;

use App\Domain\Cargo\Exceptions\InvalidCargoQuantity;
use App\Domain\Cargo\Support\DecimalMath;
use App\Domain\Cargo\ValueObjects\MassBalance;

class TransitLossCalculator
{
    public const TRANSIT_LOSS_RATE = '0.005';

    public function calculate(string $grossMass): MassBalance
    {
        $grossMinor = DecimalMath::toMinorUnits($grossMass);

        if ($grossMinor < 0) {
            throw new InvalidCargoQuantity('Gross mass cannot be negative.');
        }

        // loss = gross * 0.005, rounded to the configured three-decimal scale.
        $lossMinor = intdiv(($grossMinor * 5) + 500, 1000);
        $netMinor = $grossMinor - $lossMinor;

        return new MassBalance(
            gross: DecimalMath::fromMinorUnits($grossMinor),
            loss: DecimalMath::fromMinorUnits($lossMinor),
            net: DecimalMath::fromMinorUnits($netMinor),
        );
    }
}
