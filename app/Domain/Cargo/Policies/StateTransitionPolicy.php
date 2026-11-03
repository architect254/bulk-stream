<?php

namespace App\Domain\Cargo\Policies;

use App\Domain\Cargo\Exceptions\InvalidStateTransition;
use App\Enums\DischargeStatus;
use App\Enums\TruckDispatchStatus;
use App\Enums\VesselStatus;

class StateTransitionPolicy
{
    public function assertVesselTransition(VesselStatus $from, VesselStatus $to): void
    {
        $allowed = match ($from) {
            VesselStatus::Scheduled => [VesselStatus::Arriving],
            VesselStatus::Arriving => [VesselStatus::Discharged],
            VesselStatus::Discharged => [VesselStatus::Departed],
            VesselStatus::Departed => [],
        };

        if (! in_array($to, $allowed, true)) {
            throw new InvalidStateTransition('vessel', $from->value, $to->value);
        }
    }

    public function assertDischargeTransition(DischargeStatus $from, DischargeStatus $to): void
    {
        $allowed = match ($from) {
            DischargeStatus::Draft => [DischargeStatus::Processing],
            DischargeStatus::Processing => [DischargeStatus::Completed, DischargeStatus::Failed],
            DischargeStatus::Completed => [],
            DischargeStatus::Failed => [],
        };

        if (! in_array($to, $allowed, true)) {
            throw new InvalidStateTransition('discharge', $from->value, $to->value);
        }
    }

    public function assertTruckDispatchTransition(TruckDispatchStatus $from, TruckDispatchStatus $to): void
    {
        $allowed = match ($from) {
            TruckDispatchStatus::Planned => [TruckDispatchStatus::Loading, TruckDispatchStatus::Cancelled],
            TruckDispatchStatus::Loading => [TruckDispatchStatus::Dispatched],
            TruckDispatchStatus::Dispatched => [],
            TruckDispatchStatus::Cancelled => [],
        };

        if (! in_array($to, $allowed, true)) {
            throw new InvalidStateTransition('truck dispatch', $from->value, $to->value);
        }
    }
}
