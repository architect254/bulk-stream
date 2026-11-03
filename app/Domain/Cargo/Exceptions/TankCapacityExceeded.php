<?php

namespace App\Domain\Cargo\Exceptions;

use RuntimeException;

class TankCapacityExceeded extends RuntimeException
{
    public function __construct(
        public readonly ?string $tankCode = null,
        public readonly ?string $capacity = null,
        public readonly ?string $current = null,
        public readonly ?string $incoming = null,
        public readonly ?string $available = null,
    ) {
        $message = 'Tank capacity exceeded.';

        if ($tankCode !== null) {
            $message = sprintf(
                'Cannot receive %s MT into %s. Capacity is %s MT, current is %s MT, available is %s MT.',
                $incoming ?? '0',
                $tankCode,
                $capacity ?? '0',
                $current ?? '0',
                $available ?? '0',
            );
        }

        parent::__construct($message);
    }
}
