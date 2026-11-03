<?php

namespace App\Filament\Resources\Discharges\Pages;

use App\Filament\Resources\Discharges\DischargeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDischarge extends EditRecord
{
    protected static string $resource = DischargeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
