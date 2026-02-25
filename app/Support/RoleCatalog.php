<?php

namespace App\Support;

use App\Models\Trrole;

class RoleCatalog
{
    public const SUPERADMIN_ROLE_CODE = 'A-0';

    protected static ?int $cachedSuperadminRoleId = null;
    protected static bool $resolved = false;

    public static function flush(): void
    {
        static::$cachedSuperadminRoleId = null;
        static::$resolved = false;
    }

    public static function isSuperadminRole(?Trrole $role): bool
    {
        if (! $role) {
            return false;
        }

        $code = trim((string) ($role->c_role ?? ''));
        if ($code !== '' && strcasecmp($code, static::SUPERADMIN_ROLE_CODE) === 0) {
            return true;
        }

        $name = strtolower(trim((string) ($role->n_role ?? '')));
        if ($name === 'superadmin') {
            return true;
        }

        return $name !== '' && str_contains($name, 'superadmin');
    }

    public static function superadminRoleId(): ?int
    {
        if (static::$resolved) {
            return static::$cachedSuperadminRoleId;
        }

        static::$resolved = true;

        try {
            $row = Trrole::query()
                ->select(['i_id_role', 'c_role', 'n_role'])
                ->where(function ($q) {
                    $q->where('c_role', static::SUPERADMIN_ROLE_CODE)
                      ->orWhereRaw("LOWER(COALESCE(n_role,'')) = ?", ['superadmin'])
                      ->orWhereRaw("LOWER(COALESCE(c_role,'')) = ?", ['superadmin']);
                })
                ->orderByRaw("CASE WHEN c_role = ? THEN 0 ELSE 1 END", [static::SUPERADMIN_ROLE_CODE])
                ->orderBy('i_id_role', 'asc')
                ->first();

            static::$cachedSuperadminRoleId = $row ? (int) $row->i_id_role : null;
        } catch (\Throwable) {
            static::$cachedSuperadminRoleId = null;
        }

        return static::$cachedSuperadminRoleId;
    }

    public static function isSuperadminRoleId(int $roleId): bool
    {
        if ($roleId <= 0) {
            return false;
        }

        $sid = static::superadminRoleId();
        if ($sid !== null && $roleId === $sid) {
            return true;
        }

        try {
            $r = Trrole::query()->select(['i_id_role', 'c_role', 'n_role'])->find($roleId);
            return $r ? static::isSuperadminRole($r) : false;
        } catch (\Throwable) {
            return false;
        }
    }
}