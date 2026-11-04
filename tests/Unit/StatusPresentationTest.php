<?php

namespace Tests\Unit;

use App\Enums\DischargeStatus;
use App\Enums\InventoryMovementType;
use App\Enums\TankStatus;
use App\Enums\TruckDispatchStatus;
use App\Enums\VesselStatus;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use PHPUnit\Framework\TestCase;

class StatusPresentationTest extends TestCase
{
    public function test_status_cases_expose_labels_colours_and_icons(): void
    {
        $enums = [
            VesselStatus::class,
            TankStatus::class,
            DischargeStatus::class,
            TruckDispatchStatus::class,
            InventoryMovementType::class,
        ];

        foreach ($enums as $enum) {
            foreach ($enum::cases() as $case) {
                $this->assertInstanceOf(HasLabel::class, $case);
                $this->assertInstanceOf(HasColor::class, $case);
                $this->assertInstanceOf(HasIcon::class, $case);
                $this->assertNotEmpty($case->getLabel());

                $color = $case->getColor();
                $this->assertIsString($color);
                $this->assertContains($color, ['gray', 'info', 'success', 'warning', 'danger', 'primary']);
                $this->assertNotNull($case->getIcon());
            }
        }
    }

    public function test_semantic_status_colours_are_consistent(): void
    {
        $this->assertSame('success', TankStatus::Active->getColor());
        $this->assertSame('gray', TankStatus::Inactive->getColor());

        $this->assertSame('gray', DischargeStatus::Draft->getColor());
        $this->assertSame('info', DischargeStatus::Processing->getColor());
        $this->assertSame('success', DischargeStatus::Completed->getColor());
        $this->assertSame('danger', DischargeStatus::Failed->getColor());

        $this->assertSame('gray', TruckDispatchStatus::Planned->getColor());
        $this->assertSame('info', TruckDispatchStatus::Loading->getColor());
        $this->assertSame('success', TruckDispatchStatus::Dispatched->getColor());
        $this->assertSame('gray', TruckDispatchStatus::Cancelled->getColor());

        $this->assertSame('gray', VesselStatus::Scheduled->getColor());
        $this->assertSame('info', VesselStatus::Arriving->getColor());
        $this->assertSame('success', VesselStatus::Discharged->getColor());
        $this->assertSame('gray', VesselStatus::Departed->getColor());

        $this->assertSame('success', InventoryMovementType::Inbound->getColor());
        $this->assertSame('info', InventoryMovementType::Outbound->getColor());
    }
}
