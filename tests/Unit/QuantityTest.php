<?php

namespace Tests\Unit;

use App\Filament\Support\Quantity;
use PHPUnit\Framework\TestCase;

class QuantityTest extends TestCase
{
    public function test_mass_is_formatted_with_three_decimals_and_thousands_separators(): void
    {
        $this->assertSame('1,234.500', Quantity::mass('1234.5'));
        $this->assertSame('1,234,567.891', Quantity::mass('1234567.891'));
        $this->assertSame('0.000', Quantity::mass(0));
        $this->assertSame('—', Quantity::mass(null));
        $this->assertSame('—', Quantity::mass(''));
    }

    public function test_negative_mass_keeps_its_sign(): void
    {
        $this->assertSame('-12.345', Quantity::mass('-12.345'));
    }

    public function test_percent_is_formatted_without_floating_point_arithmetic(): void
    {
        $this->assertSame('12.34', Quantity::percent(1234));
        $this->assertSame('0.00', Quantity::percent(0));
    }

    public function test_utilization_uses_tank_capacity(): void
    {
        $this->assertSame('25.00', Quantity::utilizationPercent('250', '1000'));
        $this->assertSame('83.33', Quantity::utilizationPercent('2500000', '3000000'));
        $this->assertSame('0.00', Quantity::utilizationPercent('100', '0'));
    }
}
