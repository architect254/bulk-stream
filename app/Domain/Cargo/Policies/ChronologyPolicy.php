<?php

namespace App\Domain\Cargo\Policies;

use App\Domain\Cargo\Exceptions\InvalidChronology;

class ChronologyPolicy
{
    public function assertBatchNotPosted(bool $alreadyPosted): void
    {
        if ($alreadyPosted) {
            throw new InvalidChronology('A late event cannot be inserted into an already posted batch.');
        }
    }
}
