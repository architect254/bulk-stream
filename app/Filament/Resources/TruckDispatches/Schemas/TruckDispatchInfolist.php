<?php

namespace App\Filament\Resources\TruckDispatches\Schemas;

use App\Filament\Resources\InventoryMovements\InventoryMovementResource;
use App\Filament\Support\Quantity;
use App\Models\InventoryMovement;
use App\Models\TruckDispatch;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TruckDispatchInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('reference'),
                TextEntry::make('truck_registration')
                    ->label('Truck registration'),
                TextEntry::make('driver_name')
                    ->label('Driver name'),
                TextEntry::make('commodity.code')
                    ->label('Commodity'),
                TextEntry::make('tank.code')
                    ->label('Tank'),
                TextEntry::make('quantity')
                    ->label('Quantity (MT)')
                    ->formatStateUsing(fn ($state): string => Quantity::mass($state)),
                TextEntry::make('resulting_movement')
                    ->label('Resulting inventory movement')
                    ->state(fn (TruckDispatch $record): string => (string) (InventoryMovement::query()
                        ->where('source_type', TruckDispatch::class)
                        ->where('source_id', $record->id)
                        ->value('reference') ?: '—'))
                    ->url(fn (TruckDispatch $record): ?string => ($movement = InventoryMovement::query()
                        ->where('source_type', TruckDispatch::class)
                        ->where('source_id', $record->id)
                        ->first()) ? InventoryMovementResource::getUrl('view', ['record' => $movement]) : null)
                    ->placeholder('—'),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('dispatched_at')
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
