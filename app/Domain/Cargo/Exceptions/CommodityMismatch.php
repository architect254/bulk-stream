<?php

namespace App\Domain\Cargo\Exceptions;

use RuntimeException;

class CommodityMismatch extends RuntimeException
{
    public function __construct(
        public readonly ?string $expected = null,
        public readonly ?string $actual = null,
    ) {
        $message = 'Discharge commodity does not match destination tank commodity.';

        if ($expected !== null && $actual !== null) {
            $message = sprintf('The selected tank stores %s, but this operation contains %s.', $actual, $expected);
        }

        parent::__construct($message);
    }
}
