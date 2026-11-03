<?php

namespace Tests\Unit;

use App\Domain\Cargo\Exceptions\InvalidCargoQuantity;
use App\Domain\Cargo\Services\TransitLossCalculator;
use App\Domain\Cargo\ValueObjects\MassBalance;
use PHPUnit\Framework\TestCase;

class TransitLossCalculatorTest extends TestCase
{
    private TransitLossCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new TransitLossCalculator();
    }

    public function test_standard_loss_for_ten_thousand(): void
    {
        $result = $this->calculator->calculate('10000');

        $this->assertInstanceOf(MassBalance::class, $result);
        $this->assertSame('10000.000', $result->gross);
        $this->assertSame('50.000', $result->loss);
        $this->assertSame('9950.000', $result->net);
    }

    public function test_one_metric_ton(): void
    {
        $result = $this->calculator->calculate('1');

        $this->assertSame('1.000', $result->gross);
        $this->assertSame('0.005', $result->loss);
        $this->assertSame('0.995', $result->net);
    }

    public function test_decimal_quantity(): void
    {
        $result = $this->calculator->calculate('12.345');

        $this->assertSame('12.345', $result->gross);
        $this->assertSame('0.062', $result->loss);
        $this->assertSame('12.283', $result->net);
    }

    public function test_zero(): void
    {
        $result = $this->calculator->calculate('0');

        $this->assertSame('0.000', $result->gross);
        $this->assertSame('0.000', $result->loss);
        $this->assertSame('0.000', $result->net);
    }

    public function test_rounding_boundary(): void
    {
        $result = $this->calculator->calculate('0.1');

        $this->assertSame('0.100', $result->gross);
        $this->assertSame('0.001', $result->loss);
        $this->assertSame('0.099', $result->net);
    }

    public function test_negative_quantity_is_rejected(): void
    {
        $this->expectException(InvalidCargoQuantity::class);

        $this->calculator->calculate('-1');
    }

    public function test_mass_balance_invariant(): void
    {
        $result = $this->calculator->calculate('123.456');

        $this->assertSame(
            $result->gross,
            \App\Domain\Cargo\Support\DecimalMath::add($result->loss, $result->net)
        );
    }
}
