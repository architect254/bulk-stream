<?php

namespace App\Filament\Resources\TruckDispatches;

use App\Filament\Resources\TruckDispatches\Pages\CreateTruckDispatch;
use App\Filament\Resources\TruckDispatches\Pages\EditTruckDispatch;
use App\Filament\Resources\TruckDispatches\Pages\ListTruckDispatches;
use App\Filament\Resources\TruckDispatches\Pages\ViewTruckDispatch;
use App\Filament\Resources\TruckDispatches\Schemas\TruckDispatchForm;
use App\Filament\Resources\TruckDispatches\Schemas\TruckDispatchInfolist;
use App\Filament\Resources\TruckDispatches\Tables\TruckDispatchesTable;
use App\Models\TruckDispatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TruckDispatchResource extends Resource
{
    protected static bool $shouldSkipAuthorization = true;
    public static function canEdit($record): bool
    {
        return $record->status !== \App\Enums\TruckDispatchStatus::Dispatched && $record->status !== \App\Enums\TruckDispatchStatus::Cancelled;
    }

    public static function canDelete($record): bool
    {
        return $record->status !== \App\Enums\TruckDispatchStatus::Dispatched;
    }
    protected static ?string $model = TruckDispatch::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static \UnitEnum|string|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 30;
    protected static ?string $navigationLabel = 'Truck dispatches';

    public static function form(Schema $schema): Schema
    {
        return TruckDispatchForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TruckDispatchInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TruckDispatchesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTruckDispatches::route('/'),
            'create' => CreateTruckDispatch::route('/create'),
            'view' => ViewTruckDispatch::route('/{record}'),
            'edit' => EditTruckDispatch::route('/{record}/edit'),
        ];
    }
}
