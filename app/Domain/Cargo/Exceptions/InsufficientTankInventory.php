<?php

namespace App\Domain\Cargo\Exceptions;

use RuntimeException;

class InsufficientTankInventory extends RuntimeException
{
    public function __construct(
        public readonly ?string $tankCode = null,
        public readonly ?string $available = null,
        public readonly ?string $requested = null,
    ) {
        $message = 'Insufficient tank inventory.';

        if ($tankCode !== null) {
            $message = sprintf(
                'Cannot dispatch %s MT from %s. Available stock is %s MT.',
                $requested ?? '0',
                $tankCode,
                $available ?? '0',
            );
        }

        parent::__construct($message);
    }
}
