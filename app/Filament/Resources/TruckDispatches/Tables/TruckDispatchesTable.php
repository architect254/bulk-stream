<?php

namespace App\Filament\Resources\TruckDispatches\Tables;

use App\Actions\ProcessTruckDispatch;
use App\Enums\TruckDispatchStatus;
use App\Models\TruckDispatch;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TruckDispatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('truck_registration')
                    ->label('Truck')
                    ->searchable(),
                TextColumn::make('driver_name')
                    ->label('Driver')
                    ->searchable(),
                TextColumn::make('commodity.code')
                    ->label('Commodity')
                    ->sortable(),
                TextColumn::make('tank.code')
                    ->label('Tank')
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label('Quantity')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 3))
                    ->suffix(' MT')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('dispatched_at')
                    ->dateTime()
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(TruckDispatchStatus::class),
                SelectFilter::make('commodity_id')
                    ->label('Commodity')
                    ->relationship('commodity', 'name')
                    ->getOptionLabelFromRecordUsing(fn (\App\Models\Commodity $record): string => $record->code->value),
                SelectFilter::make('tank_id')
                    ->label('Tank')
                    ->relationship('tank', 'code'),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (TruckDispatch $record): bool => $record->status !== TruckDispatchStatus::Dispatched && $record->status !== TruckDispatchStatus::Cancelled),
                Action::make('process')
                    ->label('Process')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (TruckDispatch $record): bool => $record->status === TruckDispatchStatus::Planned || $record->status === TruckDispatchStatus::Loading)
                    ->action(function (TruckDispatch $record): void {
                        try {
                            app(ProcessTruckDispatch::class)->execute($record);

                            Notification::make()
                                ->title('Truck dispatched')
                                ->success()
                                ->send();
                        } catch (\Throwable $exception) {
                            Notification::make()
                                ->title('Dispatch failed')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([]);
    }
}
