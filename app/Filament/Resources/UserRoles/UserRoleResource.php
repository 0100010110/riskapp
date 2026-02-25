<?php

namespace App\Filament\Resources\UserRoles;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\UserRoles\Pages;
use App\Filament\Resources\UserRoles\Schemas\UserRoleForm;
use App\Filament\Resources\UserRoles\Tables\UserRolesTable;
use App\Models\Truserrole;
use App\Policies\SuperadminPolicy;
use App\Support\RoleCatalog;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
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

    
    public static function canEdit(Model $record): bool
    {
        if (! parent::canEdit($record)) {
            return false;
        }

        if ($record instanceof Truserrole) {
            $roleId = (int) ($record->i_id_role ?? 0);
            if (RoleCatalog::isSuperadminRoleId($roleId) && ! SuperadminPolicy::isSuperadmin(auth()->user())) {
                return false;
            }
        }

        return true;
    }

    
    public static function canDelete(Model $record): bool
    {
        if (! parent::canDelete($record)) {
            return false;
        }

        if ($record instanceof Truserrole) {
            $roleId = (int) ($record->i_id_role ?? 0);
            if (RoleCatalog::isSuperadminRoleId($roleId) && ! SuperadminPolicy::isSuperadmin(auth()->user())) {
                return false;
            }
        }

        return true;
    }
}