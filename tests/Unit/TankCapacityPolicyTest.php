<?php

namespace Tests\Unit;

use App\Domain\Cargo\Exceptions\InvalidCargoQuantity;
use App\Domain\Cargo\Exceptions\TankCapacityExceeded;
use App\Domain\Cargo\Policies\TankCapacityPolicy;
use PHPUnit\Framework\TestCase;

class TankCapacityPolicyTest extends TestCase
{
    private TankCapacityPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new TankCapacityPolicy();
    }

    public function test_accepts_when_below_capacity(): void
    {
        $this->policy->assertCanReceive('1000', '3000', '500');

        $this->assertTrue(true);
    }

    public function test_accepts_exact_capacity(): void
    {
        $this->policy->assertCanReceive('2500', '3000', '500');

        $this->assertTrue(true);
    }

    public function test_rejects_over_capacity(): void
    {
        $this->expectException(TankCapacityExceeded::class);

        $this->policy->assertCanReceive('2500', '3000', '1000');
    }

    public function test_zero_available_capacity_with_zero_incoming_is_not_applicable(): void
    {
        $this->expectException(InvalidCargoQuantity::class);

        $this->policy->assertCanReceive('3000', '3000', '0');
    }

    public function test_negative_quantity_is_rejected(): void
    {
        $this->expectException(InvalidCargoQuantity::class);

        $this->policy->assertCanReceive('-1', '3000', '100');
    }
}
