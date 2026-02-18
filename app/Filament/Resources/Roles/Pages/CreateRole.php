<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;
}
