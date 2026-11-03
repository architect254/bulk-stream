<?php

namespace App\Domain\Cargo\ValueObjects;

class DischargeLogEntry
{
    public function __construct(
        public readonly \DateTimeImmutable $occurredAt,
        public readonly ?string $sequence = null,
        public readonly ?int $id = null,
        public readonly ?string $externalEventId = null,
    ) {
    }
}
