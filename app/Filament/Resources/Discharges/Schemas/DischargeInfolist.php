<?php

namespace App\Filament\Resources\Discharges\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DischargeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('reference'),
                TextEntry::make('vessel.name')
                    ->label('Vessel'),
                TextEntry::make('commodity.name')
                    ->label('Commodity'),
                TextEntry::make('tank.code')
                    ->label('Tank'),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('gross_mass')
                    ->formatStateUsing(fn ($state): string => is_numeric($state) ? number_format((float) $state, 3) : '-')
                    ->placeholder('-'),
                TextEntry::make('transit_loss_mass')
                    ->formatStateUsing(fn ($state): string => is_numeric($state) ? number_format((float) $state, 3) : '-')
                    ->placeholder('-'),
                TextEntry::make('net_received_mass')
                    ->formatStateUsing(fn ($state): string => is_numeric($state) ? number_format((float) $state, 3) : '-')
                    ->placeholder('-'),
                TextEntry::make('started_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('completed_at')
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
