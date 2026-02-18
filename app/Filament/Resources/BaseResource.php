<?php

namespace App\Filament\Resources;

use App\Services\RolePermissionService;
use App\Support\PermissionBitmask;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;

abstract class BaseResource extends Resource
{
    protected static ?string $menuCode = null;

    public static function getMenuCode(): string
    {
        return (string) (static::$menuCode ?? '');
    }

    
    public static function getMenuIdentifiers(): array
    {
        return array_values(array_filter([
            static::getMenuCode(),
            static::getNavigationLabel(),
            static::getModelLabel(),
            static::getPluralModelLabel(),
        ], fn ($v) => filled($v)));
    }

    protected static function perm(): RolePermissionService
    {
        return app(RolePermissionService::class);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        return static::perm()->can(static::getMenuIdentifiers(), PermissionBitmask::READ);
    }

    public static function canView(Model $record): bool
    {
        return static::perm()->can(static::getMenuIdentifiers(), PermissionBitmask::READ);
    }

    public static function canCreate(): bool
    {
        return static::perm()->can(static::getMenuIdentifiers(), PermissionBitmask::CREATE);
    }

    public static function canEdit(Model $record): bool
    {
        return static::perm()->can(static::getMenuIdentifiers(), PermissionBitmask::UPDATE);
    }

    public static function canDelete(Model $record): bool
    {
        return static::perm()->can(static::getMenuIdentifiers(), PermissionBitmask::DELETE);
    }
}
