<?php

namespace Tests\Unit;

use App\Support\OperationalTime;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class OperationalTimeTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_operational_timezone_is_africa_nairobi(): void
    {
        $this->assertSame('Africa/Nairobi', OperationalTime::timezone());
    }

    public function test_application_timezone_remains_utc(): void
    {
        $this->assertSame('UTC', config('app.timezone'));
    }

    public function test_near_midnight_utc_is_presented_in_nairobi(): void
    {
        $this->assertSame('Nov 5, 2026 01:30:00', OperationalTime::format('2026-11-04 22:30:00'));
        $this->assertSame('2026-11-05', OperationalTime::toLocal('2026-11-04 22:30:00')?->format('Y-m-d'));
    }

    public function test_null_values_are_not_formatted(): void
    {
        $this->assertSame('—', OperationalTime::format(null));
        $this->assertNull(OperationalTime::toLocal(null));
    }
}
