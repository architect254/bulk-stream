<?php

namespace App\Enums;

enum VesselStatus: string
{
    case Scheduled = 'scheduled';
    case Arriving = 'arriving';
    case Discharged = 'discharged';
    case Departed = 'departed';
}
