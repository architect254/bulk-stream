<?php

namespace App\Filament\Resources\InventoryMovements\Schemas;

use App\Filament\Resources\Discharges\DischargeResource;
use App\Filament\Resources\TruckDispatches\TruckDispatchResource;
use App\Filament\Support\Quantity;
use App\Models\Discharge;
use App\Models\InventoryMovement;
use App\Models\TruckDispatch;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class InventoryMovementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('reference')
                    ->placeholder('—'),
                TextEntry::make('tank.code')
                    ->label('Tank'),
                TextEntry::make('commodity.code')
                    ->label('Commodity'),
                TextEntry::make('movement_type')
                    ->badge(),
                TextEntry::make('quantity')
                    ->label('Quantity (MT)')
                    ->formatStateUsing(fn ($state): string => Quantity::mass($state)),
                TextEntry::make('balance_before')
                    ->label('Balance before (MT)')
                    ->formatStateUsing(fn ($state): string => Quantity::mass($state)),
                TextEntry::make('balance_after')
                    ->label('Balance after (MT)')
                    ->formatStateUsing(fn ($state): string => Quantity::mass($state)),
                TextEntry::make('source_type')
                    ->label('Source type')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '—')
                    ->placeholder('—'),
                TextEntry::make('source_id')
                    ->label('Source ID')
                    ->placeholder('—'),
                TextEntry::make('source_record')
                    ->label('Source record')
                    ->state(fn (InventoryMovement $record): string => filled($record->source_type) && class_exists((string) $record->source_type)
                        ? class_basename((string) $record->source_type).' #'.$record->source_id
                        : '—')
                    ->url(fn (InventoryMovement $record): ?string => match (true) {
                        filled($record->source_type) && class_exists((string) $record->source_type) && $record->source instanceof Discharge => DischargeResource::getUrl('view', ['record' => $record->source]),
                        filled($record->source_type) && class_exists((string) $record->source_type) && $record->source instanceof TruckDispatch => TruckDispatchResource::getUrl('view', ['record' => $record->source]),
                        default => null,
                    })
                    ->placeholder('—'),
                TextEntry::make('idempotency_key')
                    ->placeholder('—'),
                TextEntry::make('occurred_at')
                    ->dateTime(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('—'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('—'),
            ]);
    }
}
