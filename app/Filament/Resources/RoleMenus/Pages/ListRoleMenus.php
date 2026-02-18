<?php

namespace App\Filament\Resources\RoleMenus\Pages;

use App\Filament\Resources\RoleMenus\RoleMenuResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRoleMenus extends ListRecords
{
    protected static string $resource = RoleMenuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn () => static::getResource()::canCreate()),
        ];
    }
}
