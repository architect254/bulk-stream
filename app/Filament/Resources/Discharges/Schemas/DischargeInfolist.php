<?php

namespace App\Filament\Resources\Discharges\Schemas;

use App\Filament\Resources\InventoryMovements\InventoryMovementResource;
use App\Filament\Support\Quantity;
use App\Models\Discharge;
use App\Models\InventoryMovement;
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
                TextEntry::make('commodity.code')
                    ->label('Commodity'),
                TextEntry::make('tank.code')
                    ->label('Tank'),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('gross_mass')
                    ->label('Gross (MT)')
                    ->formatStateUsing(fn ($state): string => Quantity::mass($state))
                    ->placeholder('—'),
                TextEntry::make('transit_loss_mass')
                    ->label('Transit loss (MT)')
                    ->formatStateUsing(fn ($state): string => Quantity::mass($state))
                    ->placeholder('—'),
                TextEntry::make('net_received_mass')
                    ->label('Net received (MT)')
                    ->formatStateUsing(fn ($state): string => Quantity::mass($state))
                    ->placeholder('—'),
                TextEntry::make('resulting_movement')
                    ->label('Resulting inventory movement')
                    ->state(fn (Discharge $record): string => (string) (InventoryMovement::query()
                        ->where('source_type', Discharge::class)
                        ->where('source_id', $record->id)
                        ->value('reference') ?: '—'))
                    ->url(fn (Discharge $record): ?string => ($movement = InventoryMovement::query()
                        ->where('source_type', Discharge::class)
                        ->where('source_id', $record->id)
                        ->first()) ? InventoryMovementResource::getUrl('view', ['record' => $movement]) : null)
                    ->placeholder('—'),
                TextEntry::make('started_at')
                    ->dateTime()
                    ->placeholder('—'),
                TextEntry::make('completed_at')
                    ->dateTime()
                    ->placeholder('—'),
                TextEntry::make('idempotency_key')
                    ->placeholder('—'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('—'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('—'),
            ]);
    }
}
