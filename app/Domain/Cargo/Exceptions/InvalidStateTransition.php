<?php

namespace App\Domain\Cargo\Exceptions;

use RuntimeException;

class InvalidStateTransition extends RuntimeException
{
    public function __construct(
        public readonly ?string $entity = null,
        public readonly ?string $from = null,
        public readonly ?string $to = null,
    ) {
        $message = 'Invalid state transition.';

        if ($entity !== null && $from !== null && $to !== null) {
            $message = sprintf('Invalid %s state transition: %s -> %s.', $entity, $from, $to);
        }

        parent::__construct($message);
    }
}
