<?php

namespace App\Enums;

enum VesselStatus: string implements \Filament\Support\Contracts\HasLabel, \Filament\Support\Contracts\HasColor, \Filament\Support\Contracts\HasIcon
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

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Scheduled => 'gray',
            self::Arriving => 'info',
            self::Discharged => 'success',
            self::Departed => 'gray',
        };
    }

    public function getIcon(): string | \BackedEnum | \Illuminate\Contracts\Support\Htmlable | null
    {
        return match ($this) {
            self::Scheduled => \Filament\Support\Icons\Heroicon::OutlinedClock,
            self::Arriving => \Filament\Support\Icons\Heroicon::OutlinedArrowDownTray,
            self::Discharged => \Filament\Support\Icons\Heroicon::OutlinedCheckCircle,
            self::Departed => \Filament\Support\Icons\Heroicon::OutlinedArrowUpTray,
        };
    }
}
