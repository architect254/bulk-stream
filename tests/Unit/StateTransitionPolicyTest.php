<?php

namespace Tests\Unit;

use App\Domain\Cargo\Exceptions\InvalidStateTransition;
use App\Domain\Cargo\Policies\StateTransitionPolicy;
use App\Enums\DischargeStatus;
use App\Enums\TruckDispatchStatus;
use App\Enums\VesselStatus;
use PHPUnit\Framework\TestCase;

class StateTransitionPolicyTest extends TestCase
{
    private StateTransitionPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new StateTransitionPolicy();
    }

    public function test_vessel_valid_transitions(): void
    {
        $this->policy->assertVesselTransition(VesselStatus::Scheduled, VesselStatus::Arriving);
        $this->policy->assertVesselTransition(VesselStatus::Arriving, VesselStatus::Discharged);
        $this->policy->assertVesselTransition(VesselStatus::Discharged, VesselStatus::Departed);

        $this->assertTrue(true);
    }

    public function test_vessel_invalid_transition(): void
    {
        $this->expectException(InvalidStateTransition::class);

        $this->policy->assertVesselTransition(VesselStatus::Scheduled, VesselStatus::Departed);
    }

    public function test_discharge_valid_transitions(): void
    {
        $this->policy->assertDischargeTransition(DischargeStatus::Draft, DischargeStatus::Processing);
        $this->policy->assertDischargeTransition(DischargeStatus::Processing, DischargeStatus::Completed);
        $this->policy->assertDischargeTransition(DischargeStatus::Processing, DischargeStatus::Failed);

        $this->assertTrue(true);
    }

    public function test_discharge_invalid_transition(): void
    {
        $this->expectException(InvalidStateTransition::class);

        $this->policy->assertDischargeTransition(DischargeStatus::Draft, DischargeStatus::Completed);
    }

    public function test_truck_dispatch_valid_transitions(): void
    {
        $this->policy->assertTruckDispatchTransition(TruckDispatchStatus::Planned, TruckDispatchStatus::Loading);
        $this->policy->assertTruckDispatchTransition(TruckDispatchStatus::Planned, TruckDispatchStatus::Cancelled);
        $this->policy->assertTruckDispatchTransition(TruckDispatchStatus::Loading, TruckDispatchStatus::Dispatched);

        $this->assertTrue(true);
    }

    public function test_truck_dispatch_invalid_transition(): void
    {
        $this->expectException(InvalidStateTransition::class);

        $this->policy->assertTruckDispatchTransition(TruckDispatchStatus::Dispatched, TruckDispatchStatus::Planned);
    }
}
