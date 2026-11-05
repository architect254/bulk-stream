<?php

namespace App\Filament\Resources\Discharges\RelationManagers;

use App\Filament\Resources\InventoryMovements\InventoryMovementResource;
use App\Filament\Support\Quantity;
use App\Models\InventoryMovement;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ResultingMovementRelationManager extends RelationManager
{
    protected static string $relationship = 'inventoryMovements';

    protected static ?string $title = 'Resulting inventory movement';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference')
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Time')
                    ->dateTime(),
                TextColumn::make('movement_type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('quantity')
                    ->label('Quantity (MT)')
                    ->formatStateUsing(fn ($state): string => Quantity::mass($state))
                    ->alignEnd(),
                TextColumn::make('balance_before')
                    ->label('Before (MT)')
                    ->formatStateUsing(fn ($state): string => Quantity::mass($state))
                    ->alignEnd(),
                TextColumn::make('balance_after')
                    ->label('After (MT)')
                    ->formatStateUsing(fn ($state): string => Quantity::mass($state))
                    ->alignEnd(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn (InventoryMovement $record): string => InventoryMovementResource::getUrl('view', ['record' => $record])),
            ])
            ->headerActions([])
            ->toolbarActions([])
            ->emptyStateHeading('No resulting movement')
            ->emptyStateDescription('This discharge has not been processed yet.')
            ->paginated(false);
    }
}
