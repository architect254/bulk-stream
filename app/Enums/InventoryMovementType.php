<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
}
