<?php

namespace App\Filament\Resources\InventoryMovements\Tables;

use App\Models\InventoryMovement;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InventoryMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(InventoryMovement::query()->with(['tank', 'commodity']))
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Time')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('movement_type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('tank.code')
                    ->label('Tank')
                    ->sortable(),
                TextColumn::make('commodity.code')
                    ->label('Commodity')
                    ->sortable(),
                TextColumn::make('quantity')
                    ->numeric(decimalPlaces: 3)
                    ->suffix(' MT')
                    ->sortable(),
                TextColumn::make('balance_before')
                    ->label('Before')
                    ->numeric(decimalPlaces: 3),
                TextColumn::make('balance_after')
                    ->label('After')
                    ->numeric(decimalPlaces: 3),
                TextColumn::make('source_type')
                    ->label('Source')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : '-'),
            ])
            ->filters([
                SelectFilter::make('movement_type')
                    ->options([
                        'inbound' => 'Inbound',
                        'outbound' => 'Outbound',
                    ]),
                SelectFilter::make('tank_id')
                    ->label('Tank')
                    ->relationship('tank', 'code'),
                SelectFilter::make('commodity_id')
                    ->label('Commodity')
                    ->relationship('commodity', 'name')
                    ->getOptionLabelFromRecordUsing(fn (\App\Models\Commodity $record): string => $record->code->value),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
