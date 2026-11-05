<?php

namespace App\Filament\Resources\Discharges\RelationManagers;

use App\Filament\Support\Quantity;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class LogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    protected static ?string $title = 'Source logs';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('external_event_id')
            ->columns([
                TextColumn::make('external_event_id')
                    ->label('External event ID')
                    ->searchable(),
                TextColumn::make('occurred_at')
                    ->label('Occurred at')
                    ->dateTime(),
                TextColumn::make('sequence')
                    ->label('Sequence'),
                TextColumn::make('gross_mass')
                    ->label('Gross (MT)')
                    ->formatStateUsing(fn ($state): string => Quantity::mass($state)),
                TextColumn::make('processed_at')
                    ->label('Processed at')
                    ->dateTime()
                    ->placeholder('—'),
            ])
            ->headerActions([])
            ->toolbarActions([])
            ->emptyStateHeading('No source logs')
            ->emptyStateDescription('Source logs added to this discharge will appear here.')
            ->paginated(false);
    }
}
