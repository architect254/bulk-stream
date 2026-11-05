<?php

namespace Tests\Concerns;

use Filament\Widgets\ChartWidget;

/**
 * Reads the protected presentation payloads of Filament chart widgets so that
 * chart data can be asserted without rendering Chart.js in a browser.
 */
trait ReadsChartData
{
    /**
     * @return array<string, mixed>
     */
    protected function chartData(ChartWidget $widget): array
    {
        $method = new \ReflectionMethod($widget, 'getData');
        $method->setAccessible(true);

        /** @var array<string, mixed> $data */
        $data = $method->invoke($widget);

        return $data;
    }

    protected function chartType(ChartWidget $widget): string
    {
        $method = new \ReflectionMethod($widget, 'getType');
        $method->setAccessible(true);

        return (string) $method->invoke($widget);
    }
}
