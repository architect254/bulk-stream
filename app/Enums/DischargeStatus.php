<?php

namespace App\Enums;

enum DischargeStatus: string implements \Filament\Support\Contracts\HasLabel, \Filament\Support\Contracts\HasColor, \Filament\Support\Contracts\HasIcon
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

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Processing => 'info',
            self::Completed => 'success',
            self::Failed => 'danger',
        };
    }

    public function getIcon(): string | \BackedEnum | \Illuminate\Contracts\Support\Htmlable | null
    {
        return match ($this) {
            self::Draft => \Filament\Support\Icons\Heroicon::OutlinedDocument,
            self::Processing => \Filament\Support\Icons\Heroicon::OutlinedArrowPath,
            self::Completed => \Filament\Support\Icons\Heroicon::OutlinedCheckCircle,
            self::Failed => \Filament\Support\Icons\Heroicon::OutlinedXCircle,
        };
    }
}
