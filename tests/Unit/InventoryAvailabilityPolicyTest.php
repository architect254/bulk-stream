<?php

namespace Tests\Unit;

use App\Domain\Cargo\Exceptions\InsufficientTankInventory;
use App\Domain\Cargo\Exceptions\InvalidCargoQuantity;
use App\Domain\Cargo\Policies\InventoryAvailabilityPolicy;
use PHPUnit\Framework\TestCase;

class InventoryAvailabilityPolicyTest extends TestCase
{
    private InventoryAvailabilityPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new InventoryAvailabilityPolicy();
    }

    public function test_accepts_when_less_than_current(): void
    {
        $this->policy->assertSufficient('1000', '500');

        $this->assertTrue(true);
    }

    public function test_accepts_exact_current(): void
    {
        $this->policy->assertSufficient('1000', '1000');

        $this->assertTrue(true);
    }

    public function test_rejects_when_greater_than_current(): void
    {
        $this->expectException(InsufficientTankInventory::class);

        $this->policy->assertSufficient('1000', '1500');
    }

    public function test_zero_requested_is_rejected(): void
    {
        $this->expectException(InvalidCargoQuantity::class);

        $this->policy->assertSufficient('1000', '0');
    }

    public function test_negative_requested_is_rejected(): void
    {
        $this->expectException(InvalidCargoQuantity::class);

        $this->policy->assertSufficient('1000', '-1');
    }
}
