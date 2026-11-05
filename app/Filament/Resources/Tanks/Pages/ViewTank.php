<?php

namespace App\Filament\Resources\Tanks\Pages;

use App\Filament\Resources\Tanks\TankResource;
use App\Filament\Widgets\TankBalanceChart;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewTank extends ViewRecord
{
    protected static string $resource = TankResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getInfolistContentComponent(),
                ...$this->getWidgetsSchemaComponents([TankBalanceChart::class]),
                $this->getRelationManagersContentComponent(),
            ]);
    }

    public function getWidgetData(): array
    {
        return [
            'tankId' => (int) $this->getRecord()->getKey(),
        ];
    }
}
