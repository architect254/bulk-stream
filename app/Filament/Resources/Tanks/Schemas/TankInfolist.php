<?php

namespace App\Filament\Resources\Tanks\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TankInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('code'),
                TextEntry::make('commodity.name')
                    ->label('Commodity'),
                TextEntry::make('capacity')
                    ->formatStateUsing(fn ($state): string => is_numeric($state) ? number_format((float) $state, 3) : '-'),
                TextEntry::make('current_quantity')
                    ->formatStateUsing(fn ($state): string => is_numeric($state) ? number_format((float) $state, 3) : '-'),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
