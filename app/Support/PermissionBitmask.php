<?php

namespace App\Support;

final class PermissionBitmask
{
    public const CREATE  = 1;   // 00001
    public const READ    = 2;   // 00010
    public const UPDATE  = 4;   // 00100
    public const DELETE  = 8;   // 01000
    public const APPROVE = 16;  // 10000

    public static function has(int $mask, int $permission): bool
    {
        return ($mask & $permission) === $permission;
    }
}
