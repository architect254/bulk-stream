<?php

namespace App\Domain\Cargo\Policies;

use App\Domain\Cargo\Exceptions\InvalidChronology;

class ChronologyPolicy
{
    public function assertNotFuture(\DateTimeInterface $occurredAt, ?\DateTimeInterface $now = null): void
    {
        $now ??= new \DateTimeImmutable;

        if ($occurredAt > $now) {
            throw new InvalidChronology('Source event timestamp cannot be in the future.');
        }
    }

    public function assertBatchNotPosted(bool $alreadyPosted): void
    {
        if ($alreadyPosted) {
            throw new InvalidChronology('A late event cannot be inserted into an already posted batch.');
        }
    }
}
