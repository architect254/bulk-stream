<?php

namespace App\Domain\Cargo\Exceptions;

use RuntimeException;

class CommodityMismatch extends RuntimeException
{
    public function __construct(
        public readonly ?string $expected = null,
        public readonly ?string $actual = null,
    ) {
        parent::__construct('Discharge commodity does not match destination tank commodity.');
    }
}
