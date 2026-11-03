<?php

namespace App\Domain\Cargo\Support;

use App\Domain\Cargo\Exceptions\InvalidCargoQuantity;

class DecimalMath
{
    public const SCALE = 3;
    private const FACTOR = 1000;

    public static function toMinorUnits(string $value): int
    {
        $value = trim($value);

        if ($value === '' || ! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            throw new InvalidCargoQuantity('Quantity must be a decimal number.');
        }

        $negative = str_starts_with($value, '-');
        $absolute = ltrim($value, '-');

        [$integerPart, $fractionPart] = array_pad(explode('.', $absolute, 2), 2, '');
        $fractionPart = rtrim($fractionPart, '0');
        // Keep trailing zeros significant for scale conversion by padding later.
        $fractionPart = str_pad($fractionPart, 0, '0');

        // Re-extract without rtrim to preserve scale.
        [$integerPart, $fractionPart] = array_pad(explode('.', $absolute, 2), 2, '');
        $decimals = strlen($fractionPart);

        if ($decimals <= self::SCALE) {
            $minor = (int) ($integerPart . str_pad($fractionPart, self::SCALE, '0'));
        } else {
            $divisor = 10 ** ($decimals - self::SCALE);
            $half = (int) ($divisor / 2);
            $digits = $integerPart . $fractionPart;
            $minor = intdiv((int) $digits + $half, $divisor);
        }

        return $negative ? -$minor : $minor;
    }

    public static function fromMinorUnits(int $minorUnits): string
    {
        $negative = $minorUnits < 0;
        $absolute = abs($minorUnits);

        $whole = intdiv($absolute, self::FACTOR);
        $fraction = $absolute % self::FACTOR;

        $result = sprintf('%d.%03d', $whole, $fraction);

        return $negative ? '-' . $result : $result;
    }

    public static function compare(string $left, string $right): int
    {
        return self::toMinorUnits($left) <=> self::toMinorUnits($right);
    }

    public static function add(string $left, string $right): string
    {
        return self::fromMinorUnits(self::toMinorUnits($left) + self::toMinorUnits($right));
    }

    public static function subtract(string $left, string $right): string
    {
        return self::fromMinorUnits(self::toMinorUnits($left) - self::toMinorUnits($right));
    }
}
