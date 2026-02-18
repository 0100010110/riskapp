<?php

namespace App\Services;

use App\Helper\PermissionHelper;
use App\Models\Trmenu;
use App\Models\Trrolemenu;
use App\Models\Truserrole;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class RolePermissionService
{
    public function isSuperuser(?User $user = null): bool
{
    $user ??= Auth::user();
    if (! $user) {
        return false;
    }

    // user 2542 superadmin
    if ((int) $user->id === 2542) {
        return true;
    }

    $raw = (string) env('SUPERUSER_IDS', '');
    $ids = collect(array_filter(array_map('trim', explode(',', $raw))))
        ->map(fn ($v) => (int) $v)
        ->filter(fn ($v) => $v > 0)
        ->all();

    return in_array((int) $user->id, $ids, true);
}


    public function actionForMenu(string|array $menuIdentifiers, ?User $user = null): int
    {
        $user ??= Auth::user();
        if (! $user) {
            return 0;
        }

        $identifiers = is_array($menuIdentifiers) ? $menuIdentifiers : [$menuIdentifiers];
        $keys = $this->buildLookupKeys($identifiers);
        if (empty($keys)) {
            return 0;
        }

        if ($this->isSuperuser($user)) {
            return PermissionHelper::ALL;
        }

        $req = null;
        if (! app()->runningInConsole()) {
            try {
                $req = request();
            } catch (\Throwable $e) {
                $req = null;
            }
        }

        $localKey = 'perm_local:' . (string) $user->id . ':' . md5(implode('|', $keys));
        if ($req && $req->attributes->has($localKey)) {
            return (int) $req->attributes->get($localKey);
        }

        $menu = $this->resolveMenuByKeys($keys);
        if (! $menu || ! $menu->f_active) {
            if ($req) {
                $req->attributes->set($localKey, 0);
            }
            return 0;
        }

        $roleIds = $this->resolveRoleIdsForUser($user);

        if ($roleIds->isEmpty()) {
            if ($req) {
                $req->attributes->set($localKey, 0);
            }
            return 0;
        }

        $actions = Trrolemenu::query()
            ->whereIn('i_id_role', $roleIds)
            ->where('i_id_menu', (int) $menu->i_id_menu)
            ->where('f_active', true)
            ->pluck('c_action')
            ->map(fn ($v) => (int) $v);

        $mask = 0;
        foreach ($actions as $a) {
            $mask |= $a;
        }

        $mask = $this->normalizeMaskForMenu($mask, $menu);

        if ($req) {
            $req->attributes->set($localKey, $mask);
        }

        return $mask;
    }

    public function can(string|array $menuIdentifiers, int $permission, ?User $user = null): bool
    {
        $mask = $this->actionForMenu($menuIdentifiers, $user);
        return PermissionHelper::has($mask, $permission);
    }

    public function canCrud(string|array $menuIdentifiers, int $permission, ?User $user = null): bool
    {
        $mask = $this->actionForMenu($menuIdentifiers, $user);
        return PermissionHelper::hasAll($mask, PermissionHelper::READ | $permission);
    }

    protected function resolveRoleIdsForUser(User $user)
    {
        $uid = (int) $user->id;

        $roleIds = Truserrole::query()
            ->where('i_id_user', $uid)
            ->pluck('i_id_role')
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values();

        if ($roleIds->isNotEmpty()) {
            return $roleIds;
        }

        $nikRaw = (string) ($user->nik ?? '');
        $nikRaw = trim($nikRaw);
        if ($nikRaw !== '' && ctype_digit($nikRaw)) {
            $nik = (int) $nikRaw;
            if ($nik > 0 && $nik !== $uid) {
                $roleIds = Truserrole::query()
                    ->where('i_id_user', $nik)
                    ->pluck('i_id_role')
                    ->map(fn ($v) => (int) $v)
                    ->filter(fn ($v) => $v > 0)
                    ->unique()
                    ->values();
            }
        }

        return $roleIds;
    }

    protected function normalize(?string $value): string
    {
        return strtolower(trim((string) $value));
    }

    protected function buildLookupKeys(array $identifiers): array
    {
        $keys = [];

        foreach ($identifiers as $identifier) {
            $identifier = (string) $identifier;
            $identifier = trim($identifier);
            if ($identifier === '') {
                continue;
            }

            $keys[] = $this->normalize($identifier);
            $keys[] = Str::slug($identifier);
        }

        return array_values(array_unique(array_filter($keys, fn ($v) => $v !== '')));
    }

    protected function normalizeMaskForMenu(int $mask, Trmenu $menu): int
    {
        $menuCode = $this->normalize($menu->c_menu ?? '');

        $hasApprove = PermissionHelper::has($mask, 16); // 16 = APPROVE

        $isRiskApprovalMenu = (
            $menuCode === 'risk_approvals'
            || $menuCode === 'riskapprove'
            || $menuCode === 'riskapproval'
            || $menuCode === 'risk_approval'
            || (str_contains($menuCode, 'risk') && str_contains($menuCode, 'approve'))
        );

        if ($isRiskApprovalMenu && $hasApprove) {
            $mask |= (PermissionHelper::CREATE | PermissionHelper::READ | PermissionHelper::UPDATE);

            $mask &= ~PermissionHelper::DELETE;
        }

        return $mask;
    }

    protected function resolveMenuByKeys(array $keys): ?Trmenu
    {
        static $menuMap = null;

        if ($menuMap === null) {
            $menuMap = [];

            $menus = Trmenu::query()
                ->select(['i_id_menu', 'c_menu', 'n_menu', 'f_active'])
                ->get();

            foreach ($menus as $menu) {
                $c = $this->normalize($menu->c_menu);
                $n = $this->normalize($menu->n_menu);
                $cSlug = Str::slug((string) $menu->c_menu);
                $nSlug = Str::slug((string) $menu->n_menu);

                foreach ([$c, $cSlug, $n, $nSlug] as $k) {
                    if ($k === '' || isset($menuMap[$k])) {
                        continue;
                    }
                    $menuMap[$k] = $menu;
                }
            }
        }

        foreach ($keys as $k) {
            $k = $this->normalize($k);
            if ($k === '') {
                continue;
            }
            if (isset($menuMap[$k])) {
                $menu = $menuMap[$k];
                return ($menu instanceof Trmenu) ? $menu : null;
            }
        }

        foreach ($keys as $k) {
            $k = $this->normalize($k);
            if ($k === '' || strlen($k) < 6) {
                continue;
            }

            $bestMenu = null;
            $bestScore = PHP_INT_MAX;

            foreach ($menuMap as $mapKey => $menu) {
                if ($mapKey === '') {
                    continue;
                }

                if (str_starts_with($mapKey, $k) || str_starts_with($k, $mapKey)) {
                    $score = abs(strlen($mapKey) - strlen($k));
                    if ($score < $bestScore) {
                        $bestScore = $score;
                        $bestMenu = $menu;
                    }
                }
            }

            if ($bestMenu) {
                return ($bestMenu instanceof Trmenu) ? $bestMenu : null;
            }
        }

        return null;
    }
}
