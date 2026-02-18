<?php

namespace App\Filament\Resources\UserRoles;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\UserRoles\Pages;
use App\Filament\Resources\UserRoles\Schemas\UserRoleForm;
use App\Filament\Resources\UserRoles\Tables\UserRolesTable;
use App\Models\Truserrole;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class UserRoleResource extends BaseResource
{
    protected static ?string $model = Truserrole::class;

    protected static ?string $menuCode = 'user_roles';

    protected static UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'User Roles';
    protected static ?string $pluralModelLabel = 'User Roles';
    protected static ?string $modelLabel = 'User Role';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-identification';

    public static function form(Schema $schema): Schema
    {
        return UserRoleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UserRolesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUserRoles::route('/'),
            'create' => Pages\CreateUserRole::route('/create'),
            'edit'   => Pages\EditUserRole::route('/{record}/edit'),
        ];
    }
}
