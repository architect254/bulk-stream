<?php

namespace App\Filament\Resources\TruckDispatches\Schemas;

use App\Domain\Cargo\Support\DecimalMath;
use App\Enums\TruckDispatchStatus;
use App\Models\Tank;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class TruckDispatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dispatch')
                    ->schema([
                        TextInput::make('reference')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('truck_registration')
                            ->label('Truck registration')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('driver_name')
                            ->label('Driver name')
                            ->required()
                            ->maxLength(255),
                        Select::make('commodity_id')
                            ->relationship('commodity', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),
                        Select::make('tank_id')
                            ->relationship('tank', 'code')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),
                        TextInput::make('quantity')
                            ->label('Quantity (MT)')
                            ->numeric()
                            ->required()
                            ->minValue(0.001)
                            ->live(onBlur: true),
                        Select::make('status')
                            ->options(TruckDispatchStatus::class)
                            ->default(TruckDispatchStatus::Planned)
                            ->disabled()
                            ->dehydrated(),
                        TextInput::make('idempotency_key')
                            ->default(null),
                    ])
                    ->columns(2),

                Section::make('Inventory preview')
                    ->schema([
                        Placeholder::make('current_stock')
                            ->label('Current tank inventory')
                            ->content(fn (Get $get): string => self::currentStock($get) . ' MT'),
                        Placeholder::make('requested_quantity')
                            ->label('Requested quantity')
                            ->content(fn (Get $get): string => self::requestedQuantity($get) . ' MT'),
                        Placeholder::make('projected_remaining')
                            ->label('Projected remaining inventory')
                            ->content(fn (Get $get): string => self::projectedRemaining($get) . ' MT'),
                        Placeholder::make('feasibility')
                            ->label('Appears feasible')
                            ->content(fn (Get $get): string => self::feasible($get) ? 'Yes' : 'No'),
                    ])
                    ->columns(2),
            ]);
    }

    protected static function tank(Get $get): ?Tank
    {
        $tankId = $get('tank_id');

        return $tankId ? Tank::find($tankId) : null;
    }

    protected static function currentStock(Get $get): string
    {
        $tank = self::tank($get);

        return $tank ? (string) $tank->current_quantity : '0.000';
    }

    protected static function requestedQuantity(Get $get): string
    {
        $quantity = $get('quantity');

        return is_numeric($quantity) ? (string) $quantity : '0.000';
    }

    protected static function projectedRemaining(Get $get): string
    {
        $tank = self::tank($get);
        $quantity = self::requestedQuantity($get);

        if (! $tank) {
            return '0.000';
        }

        return DecimalMath::subtract((string) $tank->current_quantity, $quantity);
    }

    protected static function feasible(Get $get): bool
    {
        $tank = self::tank($get);
        $quantity = self::requestedQuantity($get);

        if (! $tank || ! is_numeric($quantity)) {
            return false;
        }

        return bccomp((string) $tank->current_quantity, $quantity, 3) >= 0;
    }
}
