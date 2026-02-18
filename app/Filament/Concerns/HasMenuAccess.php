<?php

namespace App\Filament\Concerns;

use App\Helper\PermissionHelper;
use App\Services\RolePermissionService;

trait HasMenuAccess
{
    abstract public static function menuCode(): string;

    protected static function perms(): RolePermissionService
    {
        return app(RolePermissionService::class);
    }

    public static function actionMask(): int
    {
        return static::perms()->actionForMenu(static::menuCode());
    }

    public static function canReadMenu(): bool
    {
        return static::perms()->can(static::menuCode(), PermissionHelper::READ);
    }

    public static function canCreateMenu(): bool
    {
        return static::perms()->canCrud(static::menuCode(), PermissionHelper::CREATE);
    }

    public static function canUpdateMenu(): bool
    {
        return static::perms()->canCrud(static::menuCode(), PermissionHelper::UPDATE);
    }

    public static function canDeleteMenu(): bool
    {
        return static::perms()->canCrud(static::menuCode(), PermissionHelper::DELETE);
    }

    public static function shouldShowInNavigation(): bool
    {
        return static::canReadMenu();
    }
}
