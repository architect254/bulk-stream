<?php

namespace App\Filament\Resources\Tanks\Tables;

use App\Domain\Cargo\Support\DecimalMath;
use App\Filament\Support\Quantity;
use App\Filament\Tables\Columns\MassColumn;
use App\Models\Tank;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
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
                MassColumn::make('current_quantity')
                    ->label('Current (MT)')
                    ->sortable(),
                MassColumn::make('capacity')
                    ->label('Capacity (MT)')
                    ->sortable(),
                MassColumn::make('available_quantity')
                    ->label('Available (MT)')
                    ->state(function (Tank $record): string {
                        $available = DecimalMath::toMinorUnits((string) $record->capacity)
                            - DecimalMath::toMinorUnits((string) $record->current_quantity);

                        return DecimalMath::fromMinorUnits(max(0, $available));
                    }),
                TextColumn::make('utilization')
                    ->label('Utilization')
                    ->state(fn (Tank $record): string => Quantity::utilizationPercent(
                        (string) $record->current_quantity,
                        (string) $record->capacity,
                    ))
                    ->alignEnd()
                    ->extraAttributes(['class' => 'tabular-nums'])
                    ->suffix('%'),
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
            ->toolbarActions([])
            ->emptyStateHeading('No tanks configured')
            ->emptyStateDescription('Create a storage tank before receiving inbound cargo.')
            ->emptyStateIcon(Heroicon::OutlinedCube);
    }
}
