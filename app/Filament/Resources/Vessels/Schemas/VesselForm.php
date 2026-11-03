<?php

namespace App\Filament\Resources\Vessels\Schemas;

use App\Enums\VesselStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class VesselForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('voyage_reference')
                    ->default(null),
                Select::make('status')
                    ->options(VesselStatus::class)
                    ->default('scheduled')
                    ->required(),
            ]);
    }
}
