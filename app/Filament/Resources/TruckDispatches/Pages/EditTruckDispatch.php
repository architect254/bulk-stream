<?php

namespace App\Filament\Resources\TruckDispatches\Pages;

use App\Filament\Resources\TruckDispatches\TruckDispatchResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditTruckDispatch extends EditRecord
{
    protected static string $resource = TruckDispatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
