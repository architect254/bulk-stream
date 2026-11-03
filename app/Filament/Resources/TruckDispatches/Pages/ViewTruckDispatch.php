<?php

namespace App\Filament\Resources\TruckDispatches\Pages;

use App\Filament\Resources\TruckDispatches\TruckDispatchResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTruckDispatch extends ViewRecord
{
    protected static string $resource = TruckDispatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
