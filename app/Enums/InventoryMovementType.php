<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

enum InventoryMovementType: string implements HasColor, HasIcon, HasLabel
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

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Inbound => 'success',
            self::Outbound => 'info',
        };
    }

    public function getIcon(): string|\BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::Inbound => Heroicon::OutlinedArrowDownTray,
            self::Outbound => Heroicon::OutlinedArrowUpTray,
        };
    }
}
