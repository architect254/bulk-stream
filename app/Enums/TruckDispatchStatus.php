<?php

namespace App\Enums;

enum TruckDispatchStatus: string
{
    case Planned = 'planned';
    case Loading = 'loading';
    case Dispatched = 'dispatched';
    case Cancelled = 'cancelled';
}
