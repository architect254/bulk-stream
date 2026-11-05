<?php

namespace App\Filament\Resources\TruckDispatches\Pages;

use App\Actions\ProcessTruckDispatch;
use App\Domain\Cargo\Support\DecimalMath;
use App\Enums\TruckDispatchStatus;
use App\Filament\Resources\TruckDispatches\TruckDispatchResource;
use App\Models\TruckDispatch;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewTruckDispatch extends ViewRecord
{
    protected static string $resource = TruckDispatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('process')
                ->label('Process')
                ->icon('heroicon-o-play')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (TruckDispatch $record): bool => $record->status === TruckDispatchStatus::Planned || $record->status === TruckDispatchStatus::Loading)
                ->disabled(fn (TruckDispatch $record): bool => $record->tank === null
                    || DecimalMath::toMinorUnits((string) $record->tank->current_quantity) < DecimalMath::toMinorUnits((string) $record->quantity))
                ->tooltip(fn (TruckDispatch $record): ?string => $record->tank === null
                    ? 'Select a tank before processing.'
                    : (DecimalMath::toMinorUnits((string) $record->tank->current_quantity) < DecimalMath::toMinorUnits((string) $record->quantity)
                        ? 'Insufficient tank inventory for this quantity.'
                        : null))
                ->action(function (TruckDispatch $record): void {
                    try {
                        app(ProcessTruckDispatch::class)->execute($record->fresh(['tank', 'commodity']));

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
        ];
    }
}
