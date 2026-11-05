<?php

namespace App\Filament\Resources\Vessels\RelationManagers;

use App\Filament\Resources\Discharges\DischargeResource;
use App\Models\Discharge;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DischargesRelationManager extends RelationManager
{
    protected static string $relationship = 'discharges';

    protected static ?string $title = 'Discharges';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference')
            ->columns([
                TextColumn::make('reference')
                    ->searchable(),
                TextColumn::make('commodity.code')
                    ->label('Commodity'),
                TextColumn::make('tank.code')
                    ->label('Tank'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Discharge $record): string => DischargeResource::getUrl('view', ['record' => $record])),
            ])
            ->headerActions([])
            ->toolbarActions([])
            ->emptyStateHeading('No discharges')
            ->emptyStateDescription('Discharges for this vessel will appear here.')
            ->paginated(false);
    }
}
