<?php

namespace App\Filament\Resources\InventoryMovements\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class InventoryMovementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('reference')
                    ->placeholder('-'),
                TextEntry::make('tank.code')
                    ->label('Tank')
                    ->label('Tank'),
                TextEntry::make('commodity.name')
                    ->label('Commodity'),
                TextEntry::make('movement_type')
                    ->badge(),
                TextEntry::make('quantity')
                    ->formatStateUsing(fn ($state): string => is_numeric($state) ? number_format((float) $state, 3) : '-'),
                TextEntry::make('balance_before')
                    ->formatStateUsing(fn ($state): string => is_numeric($state) ? number_format((float) $state, 3) : '-'),
                TextEntry::make('balance_after')
                    ->formatStateUsing(fn ($state): string => is_numeric($state) ? number_format((float) $state, 3) : '-'),
                TextEntry::make('source_type')
                    ->placeholder('-'),
                TextEntry::make('source_id')
                    ->formatStateUsing(fn ($state): string => is_numeric($state) ? number_format((float) $state, 3) : '-')
                    ->placeholder('-'),
                TextEntry::make('idempotency_key')
                    ->placeholder('-'),
                TextEntry::make('occurred_at')
                    ->dateTime(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
