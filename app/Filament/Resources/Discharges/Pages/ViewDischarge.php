<?php

namespace App\Filament\Resources\Discharges\Pages;

use App\Filament\Resources\Discharges\DischargeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDischarge extends ViewRecord
{
    protected static string $resource = DischargeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
