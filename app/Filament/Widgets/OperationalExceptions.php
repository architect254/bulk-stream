<?php

namespace App\Filament\Widgets;

use App\Application\Dashboard\TerminalDashboardReadModel;
use App\Filament\Resources\Discharges\DischargeResource;
use App\Filament\Resources\Tanks\TankResource;
use App\Filament\Resources\TruckDispatches\TruckDispatchResource;
use App\Filament\Resources\Vessels\VesselResource;
use App\Filament\Support\Quantity;
use App\Models\Discharge;
use App\Models\Tank;
use App\Models\TruckDispatch;
use App\Models\Vessel;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class OperationalExceptions extends Widget
{
    protected static ?bool $isLazyLoaded = false;

    protected static ?int $sort = 40;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.operational-exceptions';

    protected function getViewData(): array
    {
        $dashboard = app(TerminalDashboardReadModel::class);

        $groups = collect($dashboard->attentionGroups())
            ->map(fn (array $group): array => [
                ...$group,
                'records' => $group['records']
                    ->map(fn (Model $record): array => $this->presentRecord($record))
                    ->all(),
                'url' => $this->indexUrl($group['key']),
            ])
            ->all();

        return [
            'groups' => $groups,
        ];
    }

    /**
     * @return array{title: string, description: string, url: string}
     */
    private function presentRecord(Model $record): array
    {
        return match (true) {
            $record instanceof Discharge => [
                'title' => $record->reference,
                'description' => trim(implode(' → ', array_filter([
                    $record->vessel?->name,
                    $record->tank?->code,
                ]))),
                'url' => DischargeResource::getUrl('view', ['record' => $record]),
            ],
            $record instanceof TruckDispatch => [
                'title' => $record->reference,
                'description' => trim(implode(' · ', array_filter([
                    $record->truck_registration,
                    $record->tank?->code,
                ]))),
                'url' => TruckDispatchResource::getUrl('view', ['record' => $record]),
            ],
            $record instanceof Tank => [
                'title' => $record->code,
                'description' => trim(implode(' · ', array_filter([
                    $record->commodity?->code?->value,
                    Quantity::mass($record->current_quantity).' MT',
                ]))),
                'url' => TankResource::getUrl('view', ['record' => $record]),
            ],
            $record instanceof Vessel => [
                'title' => $record->name,
                'description' => $record->voyage_reference ?: ($record->status?->getLabel() ?? ''),
                'url' => VesselResource::getUrl('view', ['record' => $record]),
            ],
            default => [
                'title' => class_basename($record),
                'description' => '',
                'url' => '#',
            ],
        };
    }

    private function indexUrl(string $key): string
    {
        return match ($key) {
            'failed-discharges', 'processing-discharges', 'draft-discharges' => DischargeResource::getUrl('index'),
            'tanks-over-capacity', 'negative-tank-balances', 'inactive-tanks-with-inventory' => TankResource::getUrl('index'),
            'loading-dispatches', 'planned-dispatches' => TruckDispatchResource::getUrl('index'),
            'arriving-vessels' => VesselResource::getUrl('index'),
            default => '#',
        };
    }
}
