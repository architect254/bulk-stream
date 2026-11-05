<?php

namespace App\Filament\Resources\Tanks\Schemas;

use App\Domain\Cargo\Support\DecimalMath;
use App\Filament\Support\Quantity;
use App\Models\Tank;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TankInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('code')
                    ->label('Tank'),
                TextEntry::make('commodity.code')
                    ->label('Commodity'),
                TextEntry::make('capacity')
                    ->label('Capacity (MT)')
                    ->formatStateUsing(fn ($state): string => Quantity::mass($state)),
                TextEntry::make('current_quantity')
                    ->label('Current inventory (MT)')
                    ->formatStateUsing(fn ($state): string => Quantity::mass($state)),
                TextEntry::make('remaining_capacity')
                    ->label('Remaining capacity (MT)')
                    ->state(fn (Tank $record): string => Quantity::mass(DecimalMath::subtract((string) $record->capacity, (string) $record->current_quantity))),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('—'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('—'),
            ]);
    }
}
