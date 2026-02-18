<?php

namespace App\Helper;

final class PermissionHelper
{
    // bitmask (2^n)
    public const CREATE  = 1;   // 2^0
    public const READ    = 2;   // 2^1
    public const UPDATE  = 4;   // 2^2
    public const DELETE  = 8;   // 2^3
    public const APPROVE = 16;  // 2^4

    public const ALL = self::CREATE | self::READ | self::UPDATE | self::DELETE | self::APPROVE; // 31

    public static function has(int $mask, int $permission): bool
    {
        return ($mask & $permission) === $permission;
    }

    public static function hasAny(int $mask, int $permissions): bool
    {
        return ($mask & $permissions) !== 0;
    }

    public static function hasAll(int $mask, int $permissions): bool
    {
        return ($mask & $permissions) === $permissions;
    }
}
