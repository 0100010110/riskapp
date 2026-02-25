<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Models\Trrole;
use App\Support\RoleCatalog;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Role')
                ->columnSpanFull()
                ->columns(2)
                ->schema([
                    TextInput::make('c_role')
                        ->label('Role Code')
                        ->required()
                        ->maxLength(50)
                        ->disabled(function (string $operation, ?Trrole $record): bool {
                            return $operation === 'edit' && RoleCatalog::isSuperadminRole($record);
                        }),

                    TextInput::make('n_role')
                        ->label('Role Name')
                        ->required()
                        ->maxLength(255)
                        ->disabled(function (string $operation, ?Trrole $record): bool {
                            return $operation === 'edit' && RoleCatalog::isSuperadminRole($record);
                        }),

                    Textarea::make('e_role')
                        ->label('Description')
                        ->rows(4)
                        ->columnSpanFull()
                        ->disabled(function (string $operation, ?Trrole $record): bool {
                            return $operation === 'edit' && RoleCatalog::isSuperadminRole($record);
                        }),

                    Toggle::make('f_active')
                        ->label('Active')
                        ->default(true)
                        ->disabled(function (string $operation, ?Trrole $record): bool {
                            return $operation === 'edit' && RoleCatalog::isSuperadminRole($record);
                        }),
                ]),
        ]);
    }
}