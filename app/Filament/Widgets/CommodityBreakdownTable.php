<?php

namespace App\Filament\Widgets;

use App\Application\Dashboard\OperationalAnalyticsReadModel;
use App\Filament\Tables\Columns\MassColumn;
use App\Filament\Widgets\Concerns\UsesAnalyticsPeriod;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class CommodityBreakdownTable extends TableWidget
{
    use UsesAnalyticsPeriod;

    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 60;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->records(fn () => app(OperationalAnalyticsReadModel::class)->commoditySummary(...$this->analyticsPeriod()))
            ->heading('Commodity breakdown')
            ->description('Selected-period throughput and current stock by commodity. Current stock is measured now, not at the end of the period.')
            ->columns([
                TextColumn::make('code')
                    ->label('Commodity')
                    ->weight('medium')
                    ->description(fn (array $record): string => (string) $record['name']),
                MassColumn::make('received_gross_mass')
                    ->label('Received gross (MT)'),
                MassColumn::make('transit_loss_mass')
                    ->label('Transit loss (MT)'),
                MassColumn::make('received_net_mass')
                    ->label('Received net (MT)'),
                MassColumn::make('dispatched_quantity')
                    ->label('Dispatched (MT)'),
                MassColumn::make('current_inventory')
                    ->label('Current stock (MT)')
                    ->tooltip('Current active-tank inventory, measured now rather than at the end of the period.'),
            ])
            ->paginated(false)
            ->selectable(false)
            ->recordActions([])
            ->emptyStateHeading('No commodities configured')
            ->emptyStateDescription('Commodity throughput will appear here once commodities are configured.');
    }
}
