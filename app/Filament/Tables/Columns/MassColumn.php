<?php

namespace App\Filament\Tables\Columns;

use App\Filament\Support\Quantity;
use Filament\Tables\Columns\TextColumn;

/**
 * Reusable three-decimal mass column convention for Filament tables.
 */
final class MassColumn
{
    public static function make(string $name): TextColumn
    {
        return TextColumn::make($name)
            ->formatStateUsing(fn ($state): string => Quantity::mass($state))
            ->alignEnd()
            ->extraAttributes(['class' => 'tabular-nums']);
    }
}
