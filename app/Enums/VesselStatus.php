<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

enum VesselStatus: string implements HasColor, HasIcon, HasLabel
{
    case Scheduled = 'scheduled';
    case Arriving = 'arriving';
    case Discharged = 'discharged';
    case Departed = 'departed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Arriving => 'Arriving',
            self::Discharged => 'Discharged',
            self::Departed => 'Departed',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Scheduled => 'gray',
            self::Arriving => 'info',
            self::Discharged => 'success',
            self::Departed => 'gray',
        };
    }

    public function getIcon(): string|\BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::Scheduled => Heroicon::OutlinedClock,
            self::Arriving => Heroicon::OutlinedArrowDownTray,
            self::Discharged => Heroicon::OutlinedCheckCircle,
            self::Departed => Heroicon::OutlinedArrowUpTray,
        };
    }
}
