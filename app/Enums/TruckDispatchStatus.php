<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

enum TruckDispatchStatus: string implements HasColor, HasIcon, HasLabel
{
    case Planned = 'planned';
    case Loading = 'loading';
    case Dispatched = 'dispatched';
    case Cancelled = 'cancelled';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Planned => 'Planned',
            self::Loading => 'Loading',
            self::Dispatched => 'Dispatched',
            self::Cancelled => 'Cancelled',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Planned => 'gray',
            self::Loading => 'info',
            self::Dispatched => 'success',
            self::Cancelled => 'gray',
        };
    }

    public function getIcon(): string|\BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::Planned => Heroicon::OutlinedTruck,
            self::Loading => Heroicon::OutlinedArrowPath,
            self::Dispatched => Heroicon::OutlinedCheckCircle,
            self::Cancelled => Heroicon::OutlinedXCircle,
        };
    }
}
