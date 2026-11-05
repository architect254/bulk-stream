<?php

namespace App\Filament\Support;

/**
 * Centralised colour tokens for Filament chart widgets.
 *
 * Chart.js receives plain colour strings, so the palette is declared once here
 * instead of being scattered through individual widget classes. Inbound and
 * outbound activity keep the same colour wherever they appear so that a series
 * means the same thing on the dashboard and on the analytics page.
 */
final class ChartPalette
{
    /** Completed inbound cargo (gross received mass). */
    public const INBOUND = '#0f766e';

    /** Completed inbound cargo after transit loss (net received mass). */
    public const INBOUND_NET = '#15803d';

    /** Completed outbound cargo (truck dispatches). */
    public const OUTBOUND = '#475569';

    /** Current stored inventory. */
    public const STOCK = '#0369a1';

    /** Transit loss deducted during discharge (not an inventory movement). */
    public const TRANSIT_LOSS = '#b45309';

    /** Spare tank capacity. */
    public const AVAILABLE = '#cbd5e1';

    /**
     * Commodity colours, keyed by commodity code.
     *
     * @var array<string, string>
     */
    public const COMMODITIES = [
        'CPO' => self::INBOUND,
        'CPOL' => self::STOCK,
        'PFAD' => '#b45309',
    ];

    /**
     * @return list<string>
     */
    public static function commodityColors(array $codes): array
    {
        $colors = [];

        foreach ($codes as $index => $code) {
            $colors[] = self::COMMODITIES[$code] ?? ['#64748b', '#94a3b8', '#334155'][$index % 3];
        }

        return $colors;
    }
}
