<?php

namespace App\Filament\Widgets;

use App\Models\InventoryMovement;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentMovements extends TableWidget
{
    protected static ?string $heading = 'Recent inventory movements';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(InventoryMovement::query()->with(['tank', 'commodity'])->latest('occurred_at'))
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Time')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('movement_type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('tank.code')
                    ->label('Tank'),
                TextColumn::make('commodity.code')
                    ->label('Commodity'),
                TextColumn::make('quantity')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 3))
                    ->suffix(' MT'),
                TextColumn::make('balance_before')
                    ->label('Before')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 3)),
                TextColumn::make('balance_after')
                    ->label('After')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 3)),
            ])
            ->paginated(false);
    }
}
