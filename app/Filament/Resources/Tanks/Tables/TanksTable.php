<?php

namespace App\Filament\Resources\Tanks\Tables;

use App\Models\Tank;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TanksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Tank')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('commodity.code')
                    ->label('Commodity')
                    ->sortable(),
                TextColumn::make('current_quantity')
                    ->label('Current')
                    ->numeric(decimalPlaces: 3)
                    ->suffix(' MT')
                    ->sortable(),
                TextColumn::make('capacity')
                    ->label('Capacity')
                    ->numeric(decimalPlaces: 3)
                    ->suffix(' MT')
                    ->sortable(),
                TextColumn::make('remaining')
                    ->label('Remaining')
                    ->state(fn (Tank $record): string => bcsub((string) $record->capacity, (string) $record->current_quantity, 3))
                    ->numeric(decimalPlaces: 3)
                    ->suffix(' MT'),
                TextColumn::make('utilization')
                    ->label('Utilization')
                    ->state(function (Tank $record): string {
                        if (bccomp((string) $record->capacity, '0', 3) === 0) {
                            return '0.00';
                        }

                        return bcdiv(bcmul((string) $record->current_quantity, '100', 3), (string) $record->capacity, 2);
                    })
                    ->suffix('%')
                    ->color(fn (string $state): string => (float) $state >= 90 ? 'danger' : ((float) $state >= 75 ? 'warning' : 'success')),
                TextColumn::make('status')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('commodity_id')
                    ->label('Commodity')
                    ->relationship('commodity', 'name')
                    ->getOptionLabelFromRecordUsing(fn (\App\Models\Commodity $record): string => $record->code->value),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
