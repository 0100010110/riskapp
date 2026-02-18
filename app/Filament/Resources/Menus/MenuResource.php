<?php

namespace App\Filament\Resources\Menus;

use App\Filament\Resources\BaseResource;
use App\Filament\Resources\Menus\Pages;
use App\Filament\Resources\Menus\Schemas\MenuForm;
use App\Filament\Resources\Menus\Tables\MenusTable;
use App\Models\Trmenu;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class MenuResource extends BaseResource
{
    protected static ?string $model = Trmenu::class;

    protected static ?string $menuCode = 'menus';

    protected static ?string $pluralModelLabel = 'Menu';
    protected static ?string $modelLabel = 'Menu';


    protected static UnitEnum|string|null $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'Menu';
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-bars-3-bottom-left';

    public static function form(Schema $schema): Schema
    {
        return MenuForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MenusTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMenus::route('/'),
            'create' => Pages\CreateMenu::route('/create'),
            'edit'   => Pages\EditMenu::route('/{record}/edit'),
        ];
    }
}
