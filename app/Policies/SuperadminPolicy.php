<?php

namespace App\Policies;

use App\Models\User;

class SuperadminPolicy
{
    
    public static function isSuperadmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        // 1) By name keyword (prioritas tinggi)
        $name = strtolower(trim((string) ($user->name ?? $user->n_name ?? '')));
        foreach (self::nameKeywords() as $kw) {
            if ($kw !== '' && $name !== '' && str_contains($name, $kw)) {
                return true;
            }
        }

        // 2) By user id allowlist
        $id = (int) ($user->getAuthIdentifier() ?? ($user->id ?? 0));
        if ($id > 0 && in_array($id, self::allowedIds(), true)) {
            return true;
        }

        // 3) By nik allowlist
        $nik = preg_replace('/\D+/', '', (string) ($user->nik ?? ''));
        if ($nik !== '' && in_array($nik, self::allowedNiks(), true)) {
            return true;
        }

        return false;
    }

    /**
     * @return array<int>
     */
    private static function allowedIds(): array
    {
        $raw = (string) (env('SUPERADMIN_IDS', '') ?: env('SUPERUSER_IDS', ''));

        $ids = array_filter(array_map('trim', explode(',', $raw)));
        $ids = array_map(fn ($v) => (int) $v, $ids);
        $ids = array_values(array_unique(array_filter($ids, fn ($v) => $v > 0)));

        foreach ([2875, 1706] as $defaultId) {
            if (! in_array($defaultId, $ids, true)) {
                $ids[] = $defaultId;
            }
        }

        return $ids;
    }

    /**
     * @return array<string>
     */
    private static function allowedNiks(): array
    {
        $raw = (string) env('SUPERADMIN_NIKS', '');

        $nks = array_filter(array_map('trim', explode(',', $raw)));
        $nks = array_map(fn ($v) => preg_replace('/\D+/', '', (string) $v), $nks);
        $nks = array_values(array_unique(array_filter($nks, fn ($v) => $v !== '')));

        if (! in_array('180144', $nks, true)) {
            $nks[] = '180144';
        }

        return $nks;
    }

    /**
     * @return array<string>
     */
    private static function nameKeywords(): array
    {
        $raw = (string) env('SUPERADMIN_NAME_KEYWORDS', 'Racka');

        $kws = array_filter(array_map('trim', explode(',', $raw)));
        $kws = array_map(fn ($v) => strtolower((string) $v), $kws);
        $kws = array_values(array_unique(array_filter($kws, fn ($v) => $v !== '')));

        if (! in_array('racka', $kws, true)) {
            $kws[] = 'racka';
        }

        return $kws;
    }
}