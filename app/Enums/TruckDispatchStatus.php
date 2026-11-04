<?php

namespace App\Enums;

enum TruckDispatchStatus: string implements \Filament\Support\Contracts\HasLabel, \Filament\Support\Contracts\HasColor, \Filament\Support\Contracts\HasIcon
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

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Planned => 'gray',
            self::Loading => 'info',
            self::Dispatched => 'success',
            self::Cancelled => 'gray',
        };
    }

    public function getIcon(): string | \BackedEnum | \Illuminate\Contracts\Support\Htmlable | null
    {
        return match ($this) {
            self::Planned => \Filament\Support\Icons\Heroicon::OutlinedTruck,
            self::Loading => \Filament\Support\Icons\Heroicon::OutlinedArrowPath,
            self::Dispatched => \Filament\Support\Icons\Heroicon::OutlinedCheckCircle,
            self::Cancelled => \Filament\Support\Icons\Heroicon::OutlinedXCircle,
        };
    }
}
