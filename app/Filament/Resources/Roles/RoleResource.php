<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Roles\Pages;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use App\Filament\Resources\Roles\Tables\RolesTable;
use App\Models\Trrole;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class RoleResource extends BaseResource
{
    protected static ?string $model = Trrole::class;

    protected static ?string $menuCode = 'roles';

    protected static UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Role';
    protected static ?string $pluralModelLabel = 'Role';
    protected static ?string $modelLabel = 'Role';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';

    public static function form(Schema $schema): Schema
    {
        return RoleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RolesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit'   => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
