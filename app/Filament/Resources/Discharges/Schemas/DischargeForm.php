<?php

namespace App\Filament\Resources\Discharges\Schemas;

use App\Domain\Cargo\Support\DecimalMath;
use App\Domain\Cargo\Services\TransitLossCalculator;
use App\Enums\DischargeStatus;
use App\Enums\VesselStatus;
use App\Models\Tank;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class DischargeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Discharge')
                    ->schema([
                        TextInput::make('reference')
                            ->required()
                            ->maxLength(255),
                        Select::make('vessel_id')
                            ->relationship('vessel', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),
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
                        Select::make('status')
                            ->options(DischargeStatus::class)
                            ->default(DischargeStatus::Draft)
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->columns(2),

                Section::make('Source logs')
                    ->schema([
                        Repeater::make('logs')
                            ->relationship('logs')
                            ->schema([
                                TextInput::make('external_event_id')
                                    ->label('External event ID')
                                    ->required()
                                    ->maxLength(255),
                                DateTimePicker::make('occurred_at')
                                    ->label('Occurred at')
                                    ->required()
                                    ->seconds(false),
                                TextInput::make('sequence')
                                    ->maxLength(255),
                                TextInput::make('gross_mass')
                                    ->label('Gross mass (MT)')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0.001)
                                    ->live(onBlur: true),
                            ])
                            ->columns(2)
                            ->defaultItems(1)
                            ->reorderable(false)
                            ->collapsible()
                            ->live(),
                    ]),

                Section::make('Preview')
                    ->schema([
                        Placeholder::make('total_gross')
                            ->label('Total gross mass')
                            ->content(fn (Get $get): string => self::totalGross($get) . ' MT'),
                        Placeholder::make('estimated_loss')
                            ->label('Estimated transit loss (0.5%)')
                            ->content(fn (Get $get): string => self::estimatedLoss($get) . ' MT'),
                        Placeholder::make('estimated_net')
                            ->label('Estimated net received')
                            ->content(fn (Get $get): string => self::estimatedNet($get) . ' MT'),
                        Placeholder::make('tank_current')
                            ->label('Current tank balance')
                            ->content(fn (Get $get): string => self::tankCurrent($get) . ' MT'),
                        Placeholder::make('remaining_capacity')
                            ->label('Remaining tank capacity')
                            ->content(fn (Get $get): string => self::remainingCapacity($get) . ' MT'),
                        Placeholder::make('projected_balance')
                            ->label('Projected balance after receipt')
                            ->content(fn (Get $get): string => self::projectedBalance($get) . ' MT'),
                    ])
                    ->columns(3),
            ]);
    }

    protected static function totalGross(Get $get): string
    {
        $logs = $get('logs') ?? [];
        $total = '0.000';

        foreach ($logs as $log) {
            if (isset($log['gross_mass']) && is_numeric($log['gross_mass'])) {
                $total = DecimalMath::add($total, (string) $log['gross_mass']);
            }
        }

        return $total;
    }

    protected static function estimatedLoss(Get $get): string
    {
        $total = self::totalGross($get);

        if (bccomp($total, '0', 3) <= 0) {
            return '0.000';
        }

        return (new TransitLossCalculator())->calculate($total)->loss;
    }

    protected static function estimatedNet(Get $get): string
    {
        $total = self::totalGross($get);

        if (bccomp($total, '0', 3) <= 0) {
            return '0.000';
        }

        return (new TransitLossCalculator())->calculate($total)->net;
    }

    protected static function tank(Get $get): ?Tank
    {
        $tankId = $get('tank_id');

        if (! $tankId) {
            return null;
        }

        return Tank::find($tankId);
    }

    protected static function tankCurrent(Get $get): string
    {
        $tank = self::tank($get);

        return $tank ? (string) $tank->current_quantity : '0.000';
    }

    protected static function remainingCapacity(Get $get): string
    {
        $tank = self::tank($get);

        if (! $tank) {
            return '0.000';
        }

        return bcsub((string) $tank->capacity, (string) $tank->current_quantity, 3);
    }

    protected static function projectedBalance(Get $get): string
    {
        $tank = self::tank($get);
        $net = self::estimatedNet($get);

        if (! $tank) {
            return $net;
        }

        return DecimalMath::add((string) $tank->current_quantity, $net);
    }
}
