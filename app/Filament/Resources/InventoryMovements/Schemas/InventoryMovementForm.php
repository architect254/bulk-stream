<?php

namespace App\Filament\Resources\InventoryMovements\Schemas;

use App\Enums\InventoryMovementType;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class InventoryMovementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('reference')
                    ->default(null),
                Select::make('tank_id')
                    ->relationship('tank', 'id')
                    ->required(),
                Select::make('commodity_id')
                    ->relationship('commodity', 'name')
                    ->required(),
                Select::make('movement_type')
                    ->options(InventoryMovementType::class)
                    ->required(),
                TextInput::make('quantity')
                    ->required()
                    ->numeric(),
                TextInput::make('balance_before')
                    ->required()
                    ->numeric(),
                TextInput::make('balance_after')
                    ->required()
                    ->numeric(),
                TextInput::make('source_type')
                    ->default(null),
                TextInput::make('source_id')
                    ->numeric()
                    ->default(null),
                TextInput::make('idempotency_key')
                    ->default(null),
                DateTimePicker::make('occurred_at')
                    ->required(),
            ]);
    }
}
