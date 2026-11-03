<?php

namespace App\Filament\Resources\TruckDispatches\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TruckDispatchInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('reference'),
                TextEntry::make('truck_registration'),
                TextEntry::make('driver_name'),
                TextEntry::make('commodity.name')
                    ->label('Commodity'),
                TextEntry::make('tank.code')
                    ->label('Tank')
                    ->label('Tank'),
                TextEntry::make('quantity')
                    ->formatStateUsing(fn ($state): string => is_numeric($state) ? number_format((float) $state, 3) : '-'),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('dispatched_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('idempotency_key')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
