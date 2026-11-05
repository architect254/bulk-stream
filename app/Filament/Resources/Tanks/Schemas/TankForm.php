<?php

namespace App\Filament\Resources\Tanks\Schemas;

use App\Domain\Cargo\Support\DecimalMath;
use App\Models\Tank;
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
                    ->searchable()
                    ->preload()
                    ->required()
                    ->rules([
                        function (?Tank $record) {
                            return function (string $attribute, $value, $fail) use ($record): void {
                                if ($record === null || (int) $record->commodity_id === (int) $value) {
                                    return;
                                }

                                if (DecimalMath::toMinorUnits((string) $record->current_quantity) > 0
                                    || $record->inventoryMovements()->exists()
                                    || $record->discharges()->exists()
                                    || $record->truckDispatches()->exists()) {
                                    $fail('Commodity cannot be changed while the tank has inventory or operational history.');
                                }
                            };
                        },
                    ]),
                TextInput::make('capacity')
                    ->required()
                    ->numeric()
                    ->rules([
                        function (?Tank $record) {
                            return function (string $attribute, $value, $fail) use ($record): void {
                                $current = $record?->current_quantity ?? '0.000';

                                if (DecimalMath::toMinorUnits((string) $value) < DecimalMath::toMinorUnits((string) $current)) {
                                    $fail('Capacity cannot be less than current inventory.');
                                }
                            };
                        },
                    ]),
            ]);
    }
}
