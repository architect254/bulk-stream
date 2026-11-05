<?php

namespace App\Filament\Resources\Discharges;

use App\Enums\DischargeStatus;
use App\Filament\Resources\Discharges\Pages\CreateDischarge;
use App\Filament\Resources\Discharges\Pages\EditDischarge;
use App\Filament\Resources\Discharges\Pages\ListDischarges;
use App\Filament\Resources\Discharges\Pages\ViewDischarge;
use App\Filament\Resources\Discharges\RelationManagers\LogsRelationManager;
use App\Filament\Resources\Discharges\RelationManagers\ResultingMovementRelationManager;
use App\Filament\Resources\Discharges\Schemas\DischargeForm;
use App\Filament\Resources\Discharges\Schemas\DischargeInfolist;
use App\Filament\Resources\Discharges\Tables\DischargesTable;
use App\Models\Discharge;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class DischargeResource extends Resource
{
    protected static bool $shouldSkipAuthorization = true;

    public static function canEdit($record): bool
    {
        return $record->status === DischargeStatus::Draft;
    }

    public static function canDelete($record): bool
    {
        return $record->status === DischargeStatus::Draft;
    }

    protected static ?string $model = Discharge::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static \UnitEnum|string|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Inbound discharges';

    public static function form(Schema $schema): Schema
    {
        return DischargeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DischargeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DischargesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LogsRelationManager::class,
            ResultingMovementRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDischarges::route('/'),
            'create' => CreateDischarge::route('/create'),
            'view' => ViewDischarge::route('/{record}'),
            'edit' => EditDischarge::route('/{record}/edit'),
        ];
    }
}
