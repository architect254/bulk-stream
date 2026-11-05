<?php

namespace App\Filament\Widgets;

use App\Application\Dashboard\OperationalAnalyticsReadModel;
use App\Filament\Tables\Columns\MassColumn;
use App\Filament\Widgets\Concerns\UsesAnalyticsPeriod;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class DischargeAnalysisTable extends TableWidget
{
    use UsesAnalyticsPeriod;

    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 70;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->records(fn () => app(OperationalAnalyticsReadModel::class)
                ->dischargeAnalysis(...$this->analyticsPeriod()))
            ->heading('Completed discharges and transit loss')
            ->description('Completed discharges in the selected period. Loss percentage is derived from the persisted gross and transit-loss masses; historical records are not recalculated.')
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->weight('medium'),
                TextColumn::make('vessel')
                    ->label('Vessel'),
                TextColumn::make('tank')
                    ->label('Tank'),
                TextColumn::make('commodity')
                    ->label('Commodity'),
                MassColumn::make('gross_mass')
                    ->label('Gross (MT)'),
                MassColumn::make('transit_loss_mass')
                    ->label('Loss (MT)'),
                MassColumn::make('net_received_mass')
                    ->label('Net (MT)'),
                TextColumn::make('loss_percentage')
                    ->label('Loss %')
                    ->alignEnd()
                    ->suffix('%'),
                TextColumn::make('source_log_count')
                    ->label('Logs')
                    ->alignEnd(),
                TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime()
                    ->placeholder('—'),
                TextColumn::make('resulting_movement')
                    ->label('Movement')
                    ->placeholder('—'),
            ])
            ->paginated(false)
            ->selectable(false)
            ->recordActions([])
            ->emptyStateHeading('No completed discharges')
            ->emptyStateDescription('Completed discharges in the selected period will appear here.');
    }
}
