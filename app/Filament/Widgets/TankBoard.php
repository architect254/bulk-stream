<?php

namespace App\Filament\Widgets;

use App\Domain\Cargo\Support\DecimalMath;
use App\Filament\Resources\Tanks\TankResource;
use App\Filament\Support\Quantity;
use App\Filament\Tables\Columns\MassColumn;
use App\Models\Tank;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TankBoard extends TableWidget
{
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 30;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(Tank::query()->active()->with('commodity')->orderBy('code'))
            ->heading('Tank inventory')
            ->columns([
                TextColumn::make('code')
                    ->label('Tank')
                    ->weight('medium')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('commodity.code')
                    ->label('Commodity')
                    ->sortable(),
                MassColumn::make('current_quantity')
                    ->label('Current (MT)')
                    ->sortable(),
                MassColumn::make('capacity')
                    ->label('Capacity (MT)')
                    ->sortable(),
                MassColumn::make('available_quantity')
                    ->label('Available (MT)')
                    ->state(function (Tank $record): string {
                        $available = DecimalMath::toMinorUnits((string) $record->capacity)
                            - DecimalMath::toMinorUnits((string) $record->current_quantity);

                        return DecimalMath::fromMinorUnits(max(0, $available));
                    }),
                ViewColumn::make('utilization')
                    ->label('Utilization')
                    ->state(function (Tank $record): array {
                        $basisPoints = Quantity::utilizationBasisPoints(
                            (string) $record->current_quantity,
                            (string) $record->capacity,
                        );

                        return [
                            'percent' => Quantity::percent($basisPoints),
                            'width' => max(0, min(100, $basisPoints / 100)),
                        ];
                    })
                    ->view('filament.tables.columns.utilization-bar'),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
            ])
            ->recordUrl(fn (Tank $record): string => TankResource::getUrl('view', ['record' => $record]))
            ->defaultSort('code')
            ->selectable(false)
            ->recordActions([])
            ->emptyStateHeading('No active tanks')
            ->emptyStateDescription('Active storage tanks will appear here once they are configured.')
            ->emptyStateIcon(Heroicon::OutlinedCube);
    }
}
