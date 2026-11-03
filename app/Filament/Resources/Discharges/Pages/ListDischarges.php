<?php

namespace App\Filament\Resources\Discharges\Pages;

use App\Filament\Resources\Discharges\DischargeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDischarges extends ListRecords
{
    protected static string $resource = DischargeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
