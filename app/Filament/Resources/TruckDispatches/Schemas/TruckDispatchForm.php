<?php

namespace App\Filament\Resources\TruckDispatches\Schemas;

use App\Domain\Cargo\Support\DecimalMath;
use App\Enums\TankStatus;
use App\Enums\TruckDispatchStatus;
use App\Filament\Support\Quantity;
use App\Models\Tank;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class TruckDispatchForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dispatch')
                    ->schema([
                        TextInput::make('truck_registration')
                            ->label('Truck registration')
                            ->trim()
                            ->required()
                            ->minLength(3)
                            ->maxLength(32)
                            ->rules(['regex:/^[A-Z0-9][A-Z0-9 \-]{2,31}$/i']),
                        TextInput::make('driver_name')
                            ->label('Driver name')
                            ->trim()
                            ->required()
                            ->minLength(2)
                            ->maxLength(255),
                        Select::make('tank_id')
                            ->relationship(
                                name: 'tank',
                                titleAttribute: 'code',
                                modifyQueryUsing: fn ($query) => $query->where('status', TankStatus::Active)
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                $set('commodity_id', Tank::find($state)?->commodity_id);
                            })
                            ->getOptionLabelFromRecordUsing(fn (Tank $tank): string => sprintf(
                                '%s — %s — %s / %s MT available',
                                $tank->code,
                                $tank->commodity->code->value,
                                Quantity::mass((string) $tank->current_quantity),
                                Quantity::mass((string) $tank->capacity)
                            )),
                        Select::make('commodity_id')
                            ->relationship('commodity', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled()
                            ->dehydrated(),
                        TextInput::make('quantity')
                            ->label('Quantity (MT)')
                            ->numeric()
                            ->required()
                            ->minValue(0.001)
                            ->helperText('Cannot exceed available tank inventory.')
                            ->live(onBlur: true),
                        Select::make('status')
                            ->options(TruckDispatchStatus::class)
                            ->default(TruckDispatchStatus::Planned)
                            ->disabled()
                            ->dehydrated(false),
                    ])
                    ->columns(2),

                Section::make('Inventory preview')
                    ->schema([
                        Placeholder::make('current_stock')
                            ->label('Current tank inventory')
                            ->content(fn (Get $get): string => Quantity::mass(self::currentStock($get)).' MT'),
                        Placeholder::make('requested_quantity')
                            ->label('Requested quantity')
                            ->content(fn (Get $get): string => Quantity::mass(self::requestedQuantity($get)).' MT'),
                        Placeholder::make('projected_remaining')
                            ->label('Projected remaining inventory')
                            ->content(fn (Get $get): string => Quantity::mass(self::projectedRemaining($get)).' MT'),
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

        return DecimalMath::toMinorUnits((string) $tank->current_quantity) >= DecimalMath::toMinorUnits($quantity);
    }
}
