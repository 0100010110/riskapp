<?php

namespace App\Filament\Resources\RoleMenus;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\RoleMenus\Pages;
use App\Filament\Resources\RoleMenus\Schemas\RoleMenuForm;
use App\Filament\Resources\RoleMenus\Tables\RoleMenusTable;
use App\Models\Trrolemenu;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class RoleMenuResource extends BaseResource
{
    protected static ?string $model = Trrolemenu::class;

    protected static ?string $menuCode = 'role_menus';

    protected static UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Role Menu';
    protected static ?string $pluralModelLabel = 'Role Menu';
    protected static ?string $modelLabel = 'Role Menu';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-key';

    public static function form(Schema $schema): Schema
    {
        return RoleMenuForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RoleMenusTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRoleMenus::route('/'),
            'create' => Pages\CreateRoleMenu::route('/create'),
            'edit'   => Pages\EditRoleMenu::route('/{record}/edit'),
        ];
    }
}
