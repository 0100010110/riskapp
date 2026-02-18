<?php

namespace App\Filament\Resources\Menus\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MenuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('c_menu')
                ->label('Menu Code')
                ->required()
                ->maxLength(255),

            TextInput::make('n_menu')
                ->label('Menu Name')
                ->required()
                ->maxLength(255),

            Textarea::make('e_menu')
                ->label('Description')
                ->rows(3)
                ->columnSpanFull(),

            Toggle::make('f_active')
                ->label('Active')
                ->default(true),
        ]);
    }
}
