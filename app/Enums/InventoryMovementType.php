<?php

namespace App\Enums;

enum InventoryMovementType: string implements \Filament\Support\Contracts\HasLabel, \Filament\Support\Contracts\HasColor, \Filament\Support\Contracts\HasIcon
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Inbound => 'Inbound',
            self::Outbound => 'Outbound',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Inbound => 'success',
            self::Outbound => 'info',
        };
    }

    public function getIcon(): string | \BackedEnum | \Illuminate\Contracts\Support\Htmlable | null
    {
        return match ($this) {
            self::Inbound => \Filament\Support\Icons\Heroicon::OutlinedArrowDownTray,
            self::Outbound => \Filament\Support\Icons\Heroicon::OutlinedArrowUpTray,
        };
    }
}
