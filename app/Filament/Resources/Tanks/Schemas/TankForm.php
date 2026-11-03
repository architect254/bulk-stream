<?php

namespace App\Filament\Resources\Tanks\Schemas;

use App\Enums\TankStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TankForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                Select::make('commodity_id')
                    ->relationship('commodity', 'name')
                    ->required(),
                TextInput::make('capacity')
                    ->required()
                    ->numeric(),
                Select::make('status')
                    ->options(TankStatus::class)
                    ->default('active')
                    ->required(),
            ]);
    }
}
