<?php

namespace App\Filament\Support;

use App\Domain\Cargo\Support\DecimalMath;
use Throwable;

/**
 * Presentation-only helpers for cargo quantities.
 *
 * Persisted quantities stay as DECIMAL(18,3) values. This class only formats
 * those values for the interface, using integer minor units so that display
 * formatting never changes the underlying quantity.
 */
final class Quantity
{
    public const EMPTY = '—';

    public static function mass(string|int|float|null $value): string
    {
        $normalised = self::normalise($value);

        if ($normalised === null) {
            return self::EMPTY;
        }

        try {
            $minor = DecimalMath::toMinorUnits($normalised);
        } catch (Throwable) {
            return self::EMPTY;
        }

        $factor = 10 ** DecimalMath::SCALE;
        $absolute = abs($minor);

        return ($minor < 0 ? '-' : '')
            .number_format(intdiv($absolute, $factor))
            .'.'
            .str_pad((string) ($absolute % $factor), DecimalMath::SCALE, '0', STR_PAD_LEFT);
    }

    /**
     * Format basis points (percent × 100) without floating-point arithmetic.
     */
    public static function percent(int $basisPoints): string
    {
        $absolute = abs($basisPoints);

        return ($basisPoints < 0 ? '-' : '')
            .number_format(intdiv($absolute, 100))
            .'.'
            .str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Utilization in basis points (percent × 100), rounded down.
     */
    public static function utilizationBasisPoints(string $current, string $capacity): int
    {
        $capacityMinor = DecimalMath::toMinorUnits($capacity);

        if ($capacityMinor <= 0) {
            return 0;
        }

        return intdiv(DecimalMath::toMinorUnits($current) * 10_000, $capacityMinor);
    }

    public static function utilizationPercent(string $current, string $capacity): string
    {
        return self::percent(self::utilizationBasisPoints($current, $capacity));
    }

    private static function normalise(string|int|float|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return number_format($value, DecimalMath::SCALE, '.', '');
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^-?\d+(\.\d+)?$/', $value) === 1) {
            return $value;
        }

        if (is_numeric($value)) {
            return number_format((float) $value, DecimalMath::SCALE, '.', '');
        }

        return null;
    }
}
