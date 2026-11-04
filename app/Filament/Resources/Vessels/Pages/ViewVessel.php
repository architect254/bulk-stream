<?php

namespace App\Filament\Resources\Vessels\Pages;

use App\Enums\VesselStatus;
use App\Filament\Resources\Vessels\VesselResource;
use App\Models\Vessel;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewVessel extends ViewRecord
{
    protected static string $resource = VesselResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('markArriving')
                ->label('Mark arriving')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->visible(fn (Vessel $record): bool => $record->status === VesselStatus::Scheduled)
                ->action(function (Vessel $record): void {
                    try {
                        $record->markAsArriving();

                        Notification::make()
                            ->title('Vessel marked as arriving')
                            ->success()
                            ->send();
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('Status change failed')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
