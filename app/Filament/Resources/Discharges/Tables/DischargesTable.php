<?php

namespace App\Filament\Resources\Discharges\Tables;

use App\Actions\ProcessInboundDischarge;
use App\Enums\DischargeStatus;
use App\Models\Discharge;
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

class DischargesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('vessel.name')
                    ->label('Vessel')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('commodity.code')
                    ->label('Commodity')
                    ->sortable(),
                TextColumn::make('tank.code')
                    ->label('Tank')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('gross_mass')
                    ->label('Gross (MT)')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 3))
                    
                    ->placeholder('-'),
                TextColumn::make('transit_loss_mass')
                    ->label('Loss (MT)')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 3))
                    
                    ->placeholder('-'),
                TextColumn::make('net_received_mass')
                    ->label('Net (MT)')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 3))
                    
                    ->placeholder('-'),
                TextColumn::make('started_at')
                    ->dateTime()
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('completed_at')
                    ->dateTime()
                    ->placeholder('-')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(DischargeStatus::class),
                SelectFilter::make('vessel_id')
                    ->label('Vessel')
                    ->relationship('vessel', 'name'),
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
                    ->visible(fn (Discharge $record): bool => $record->status !== DischargeStatus::Completed),
                Action::make('process')
                    ->label('Process')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Discharge $record): bool => $record->status === DischargeStatus::Draft)
                    ->action(function (Discharge $record): void {
                        try {
                            app(ProcessInboundDischarge::class)->execute($record);

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
            ])
            ->paginated(false)
            ->selectable(false)
            ->toolbarActions([]);
    }
}
