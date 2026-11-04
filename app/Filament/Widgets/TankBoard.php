<?php

namespace App\Filament\Widgets;

use App\Domain\Cargo\Support\DecimalMath;
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
                    ->label('Current (MT)')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 3))
                    
                    ->sortable(),
                TextColumn::make('capacity')
                    ->label('Capacity (MT)')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 3))
                    
                    ->sortable(),
                TextColumn::make('remaining')
                    ->label('Remaining (MT)')
                    ->state(fn (Tank $record): string => DecimalMath::subtract((string) $record->capacity, (string) $record->current_quantity))
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 3))
                    ,
                TextColumn::make('utilization')
                    ->label('Utilization')
                    ->state(function (Tank $record): string {
                        $capacityMinor = DecimalMath::toMinorUnits((string) $record->capacity);

                        if ($capacityMinor === 0) {
                            return '0.00';
                        }

                        $percent = intdiv(DecimalMath::toMinorUnits((string) $record->current_quantity) * 10000, $capacityMinor);

                        return number_format($percent / 100, 2);
                    })
                    ->suffix('%')
                    ->color(fn (string $state): string => (float) $state >= 90 ? 'danger' : ((float) $state >= 75 ? 'warning' : 'success')),
                TextColumn::make('status')
                    ->badge(),
            ])
            ->paginated(false)
            ->selectable(false);
    }
}
