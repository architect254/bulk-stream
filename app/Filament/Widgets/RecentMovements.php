<?php

namespace App\Filament\Widgets;

use App\Application\Dashboard\TerminalDashboardReadModel;
use App\Filament\Resources\Discharges\DischargeResource;
use App\Filament\Resources\InventoryMovements\InventoryMovementResource;
use App\Filament\Resources\TruckDispatches\TruckDispatchResource;
use App\Filament\Support\Quantity;
use App\Filament\Tables\Columns\MassColumn;
use App\Models\Discharge;
use App\Models\InventoryMovement;
use App\Models\TruckDispatch;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentMovements extends TableWidget
{
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 50;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->records(fn () => app(TerminalDashboardReadModel::class)->recentMovements(10))
            ->heading('Recent inventory movements')
            ->columns([
                TextColumn::make('occurred_at')
                    ->label('Time')
                    ->dateTime(),
                TextColumn::make('movement_type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('tank.code')
                    ->label('Tank'),
                TextColumn::make('source_reference')
                    ->label('Source')
                    ->state(fn (InventoryMovement $record): string => $this->sourceLabel($record))
                    ->url(fn (InventoryMovement $record): ?string => $this->sourceUrl($record)),
                MassColumn::make('quantity')
                    ->label('Quantity (MT)'),
                MassColumn::make('balance_after')
                    ->label('Balance after (MT)'),
            ])
            ->headerActions([
                Action::make('viewAll')
                    ->label('View all movements')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->url(InventoryMovementResource::getUrl('index')),
            ])
            ->paginated(false)
            ->selectable(false)
            ->recordActions([])
            ->emptyStateHeading('No inventory movements')
            ->emptyStateDescription('Inbound receipts and outbound dispatches will appear here.')
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList);
    }

    private function sourceLabel(InventoryMovement $movement): string
    {
        if (filled($movement->reference)) {
            return (string) $movement->reference;
        }

        if ($movement->source !== null) {
            $reference = $movement->source->getAttribute('reference');

            return trim(class_basename($movement->source).' '.($reference ?: '#'.$movement->source_id));
        }

        if (filled($movement->source_type)) {
            return class_basename($movement->source_type).' #'.$movement->source_id;
        }

        return Quantity::EMPTY;
    }

    private function sourceUrl(InventoryMovement $movement): ?string
    {
        return match (true) {
            $movement->source instanceof Discharge => DischargeResource::getUrl('view', ['record' => $movement->source]),
            $movement->source instanceof TruckDispatch => TruckDispatchResource::getUrl('view', ['record' => $movement->source]),
            default => null,
        };
    }
}
