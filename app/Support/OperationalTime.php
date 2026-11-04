<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

final class OperationalTime
{
    public const TIMEZONE = 'Africa/Nairobi';

    public static function timezone(): string
    {
        return (string) config('terminal.timezone', self::TIMEZONE);
    }

    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(self::timezone());
    }

    public static function toLocal(DateTimeInterface|string|null $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            $local = CarbonImmutable::instance($value);
        } else {
            $local = CarbonImmutable::parse((string) $value, 'UTC');
        }

        return $local->setTimezone(self::timezone());
    }

    public static function format(DateTimeInterface|string|null $value, string $format = 'M j, Y H:i:s'): string
    {
        $local = self::toLocal($value);

        return $local === null ? '—' : $local->format($format);
    }
}
