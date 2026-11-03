<?php

namespace App\Filament\Widgets;

use App\Models\Tank;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class TankBoard extends TableWidget
{
    protected static ?string $heading = 'Tank board';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(Tank::query()->with('commodity'))
            ->columns([
                TextColumn::make('code')
                    ->label('Tank')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('commodity.name')
                    ->label('Commodity')
                    ->sortable(),
                TextColumn::make('current_quantity')
                    ->label('Current')
                    ->numeric(decimalPlaces: 3)
                    ->suffix(' MT')
                    ->sortable(),
                TextColumn::make('capacity')
                    ->label('Capacity')
                    ->numeric(decimalPlaces: 3)
                    ->suffix(' MT')
                    ->sortable(),
                TextColumn::make('remaining')
                    ->label('Remaining')
                    ->state(fn (Tank $record): string => bcsub((string) $record->capacity, (string) $record->current_quantity, 3))
                    ->numeric(decimalPlaces: 3)
                    ->suffix(' MT'),
                TextColumn::make('utilization')
                    ->label('Utilization')
                    ->state(function (Tank $record): string {
                        if (bccomp((string) $record->capacity, '0', 3) === 0) {
                            return '0.00';
                        }

                        return bcdiv(
                            bcmul((string) $record->current_quantity, '100', 3),
                            (string) $record->capacity,
                            2
                        );
                    })
                    ->suffix('%')
                    ->color(fn (string $state): string => (float) $state >= 90 ? 'danger' : ((float) $state >= 75 ? 'warning' : 'success')),
                TextColumn::make('status')
                    ->badge(),
            ])
            ->paginated(false);
    }
}
