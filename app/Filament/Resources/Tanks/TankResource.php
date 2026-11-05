<?php

namespace App\Filament\Resources\Tanks;

use App\Filament\Resources\Tanks\Pages\CreateTank;
use App\Filament\Resources\Tanks\Pages\EditTank;
use App\Filament\Resources\Tanks\Pages\ListTanks;
use App\Filament\Resources\Tanks\Pages\ViewTank;
use App\Filament\Resources\Tanks\RelationManagers\MovementsRelationManager;
use App\Filament\Resources\Tanks\Schemas\TankForm;
use App\Filament\Resources\Tanks\Schemas\TankInfolist;
use App\Filament\Resources\Tanks\Tables\TanksTable;
use App\Models\Tank;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TankResource extends Resource
{
    protected static bool $shouldSkipAuthorization = true;

    protected static ?string $model = Tank::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static \UnitEnum|string|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Tank inventory';

    public static function form(Schema $schema): Schema
    {
        return TankForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TankInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TanksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MovementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTanks::route('/'),
            'create' => CreateTank::route('/create'),
            'view' => ViewTank::route('/{record}'),
            'edit' => EditTank::route('/{record}/edit'),
        ];
    }
}
