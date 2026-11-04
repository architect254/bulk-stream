<?php

namespace App\Enums;

enum TankStatus: string implements \Filament\Support\Contracts\HasLabel, \Filament\Support\Contracts\HasColor, \Filament\Support\Contracts\HasIcon
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Active => 'success',
            self::Inactive => 'gray',
        };
    }

    public function getIcon(): string | \BackedEnum | \Illuminate\Contracts\Support\Htmlable | null
    {
        return match ($this) {
            self::Active => \Filament\Support\Icons\Heroicon::OutlinedCheckCircle,
            self::Inactive => \Filament\Support\Icons\Heroicon::OutlinedMinusCircle,
        };
    }
}
