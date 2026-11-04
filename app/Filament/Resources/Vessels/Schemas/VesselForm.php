<?php

namespace App\Filament\Resources\Vessels\Schemas;

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
            ]);
    }
}
