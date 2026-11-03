<?php

namespace App\Filament\Resources\TruckDispatches\Pages;

use App\Filament\Resources\TruckDispatches\TruckDispatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTruckDispatches extends ListRecords
{
    protected static string $resource = TruckDispatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
