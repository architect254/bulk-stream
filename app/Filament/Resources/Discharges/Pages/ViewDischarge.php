<?php

namespace App\Filament\Resources\Discharges\Pages;

use App\Actions\ProcessInboundDischarge;
use App\Enums\DischargeStatus;
use App\Enums\VesselStatus;
use App\Filament\Resources\Discharges\DischargeResource;
use App\Filament\Widgets\DischargeMassBalanceChart;
use App\Models\Discharge;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewDischarge extends ViewRecord
{
    protected static string $resource = DischargeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('process')
                ->label('Process')
                ->icon('heroicon-o-play')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (Discharge $record): bool => $record->status === DischargeStatus::Draft)
                ->disabled(fn (Discharge $record): bool => $record->vessel?->status !== VesselStatus::Arriving || $record->logs->isEmpty())
                ->tooltip(fn (Discharge $record): ?string => $record->vessel?->status !== VesselStatus::Arriving
                    ? 'Vessel must be marked as arriving before processing.'
                    : ($record->logs->isEmpty() ? 'Add at least one source log before processing.' : null))
                ->action(function (Discharge $record): void {
                    try {
                        app(ProcessInboundDischarge::class)->execute($record->fresh(['logs', 'tank', 'commodity', 'vessel']));

                        Notification::make()
                            ->title('Discharge processed')
                            ->success()
                            ->send();
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('Processing failed')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getInfolistContentComponent(),
                ...$this->getWidgetsSchemaComponents([DischargeMassBalanceChart::class]),
                $this->getRelationManagersContentComponent(),
            ]);
    }

    public function getWidgetData(): array
    {
        return [
            'dischargeId' => (int) $this->getRecord()->getKey(),
        ];
    }
}
