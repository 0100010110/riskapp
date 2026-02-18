<?php

namespace App\Filament\Concerns;

use App\Helper\PermissionHelper;
use App\Services\RolePermissionService;

trait HasMenuBitmaskPermissions
{
    
    abstract public static function getMenuCode(): string;

    protected static function perms(): RolePermissionService
    {
        return app(RolePermissionService::class);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::perms()->can(static::getMenuCode(), PermissionHelper::READ);
    }

    public static function canViewAny(): bool
    {
        return static::perms()->can(static::getMenuCode(), PermissionHelper::READ);
    }

    public static function canView($record): bool
    {
        return static::perms()->can(static::getMenuCode(), PermissionHelper::READ);
    }

    public static function canCreate(): bool
    {
        return static::perms()->can(static::getMenuCode(), PermissionHelper::CREATE);
    }

    public static function canEdit($record): bool
    {
        return static::perms()->can(static::getMenuCode(), PermissionHelper::UPDATE);
    }

    public static function canDelete($record): bool
    {
        return static::perms()->can(static::getMenuCode(), PermissionHelper::DELETE);
    }
}
