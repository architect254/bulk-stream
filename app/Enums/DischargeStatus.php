<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

enum DischargeStatus: string implements HasColor, HasIcon, HasLabel
{
    case Draft = 'draft';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Processing => 'Processing',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Processing => 'info',
            self::Completed => 'success',
            self::Failed => 'danger',
        };
    }

    public function getIcon(): string|\BackedEnum|Htmlable|null
    {
        return match ($this) {
            self::Draft => Heroicon::OutlinedDocument,
            self::Processing => Heroicon::OutlinedArrowPath,
            self::Completed => Heroicon::OutlinedCheckCircle,
            self::Failed => Heroicon::OutlinedXCircle,
        };
    }
}
