<?php

namespace App\Domain\Cargo\ValueObjects;

use App\Domain\Cargo\Support\DecimalMath;

class MassBalance
{
    public function __construct(
        public readonly string $gross,
        public readonly string $loss,
        public readonly string $net,
    ) {
        if (DecimalMath::toMinorUnits($this->gross) !== DecimalMath::toMinorUnits($this->loss) + DecimalMath::toMinorUnits($this->net)) {
            throw new \InvalidArgumentException('Gross mass must equal loss plus net mass.');
        }
    }

    public function toArray(): array
    {
        return [
            'gross' => $this->gross,
            'loss' => $this->loss,
            'net' => $this->net,
        ];
    }
}
