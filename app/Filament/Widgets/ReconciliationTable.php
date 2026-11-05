<?php

namespace App\Filament\Widgets;

use App\Application\Dashboard\OperationalAnalyticsReadModel;
use App\Filament\Tables\Columns\MassColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ReconciliationTable extends TableWidget
{
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 80;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->records(fn () => app(OperationalAnalyticsReadModel::class)->reconciliation())
            ->heading('Inventory reconciliation')
            ->description('Read-only comparison of stored tank balances and balances reconstructed from the inventory movement ledger. A zero difference indicates reconciliation; non-zero differences are reported for investigation only.')
            ->columns([
                TextColumn::make('code')
                    ->label('Tank')
                    ->weight('medium'),
                TextColumn::make('commodity')
                    ->label('Commodity'),
                MassColumn::make('stored_balance')
                    ->label('Stored (MT)'),
                MassColumn::make('reconstructed_balance')
                    ->label('Reconstructed (MT)'),
                MassColumn::make('difference')
                    ->label('Difference (MT)'),
                TextColumn::make('movement_count')
                    ->label('Movements')
                    ->alignEnd(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Reconciled' => 'success',
                        'Discrepancy' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->paginated(false)
            ->selectable(false)
            ->recordActions([])
            ->emptyStateHeading('No tanks configured')
            ->emptyStateDescription('Tank reconciliation will appear here once tanks are configured.');
    }
}
