<?php

namespace App\Services;

use App\Helper\PermissionHelper;
use App\Models\Trmenu;
use App\Models\Trrolemenu;
use App\Models\Truserrole;
use Illuminate\Support\Facades\Auth;

class MenuPermissionService
{
    public function isSuperuser(?int $userId = null): bool
    {
        $userId ??= (int) Auth::id();
        if ($userId <= 0) {
            return false;
        }

        $ids = config('permissions.superuser_ids', []);
        return in_array($userId, $ids, true);
    }

   
    public function actionFor(string $menuCode, ?int $userId = null): int
    {
        $userId ??= (int) Auth::id();
        if ($userId <= 0) {
            return 0;
        }

        if ($this->isSuperuser($userId)) {
            return PermissionHelper::sumAllPermissions(); // 31
        }

        $menu = Trmenu::query()
            ->where('c_menu', $menuCode)
            ->where('f_active', true)
            ->first();

        if (! $menu) {
            return 0;
        }

        $roleIds = Truserrole::query()
            ->where('i_id_user', $userId)
            ->pluck('i_id_role')
            ->map(fn ($v) => (int) $v)
            ->all();

        if (empty($roleIds)) {
            return 0;
        }

        $mask = 0;

        $actions = Trrolemenu::query()
            ->whereIn('i_id_role', $roleIds)
            ->where('i_id_menu', (int) $menu->i_id_menu)
            ->pluck('c_action');

        foreach ($actions as $a) {
            $mask |= (int) $a;
        }

        return $mask;
    }

    public function canRead(string $menuCode): bool
    {
        return PermissionHelper::hasPermission($this->actionFor($menuCode), PermissionHelper::READ);
    }

    public function canCreate(string $menuCode): bool
    {
        return PermissionHelper::hasPermission($this->actionFor($menuCode), PermissionHelper::CREATE);
    }

    public function canUpdate(string $menuCode): bool
    {
        return PermissionHelper::hasPermission($this->actionFor($menuCode), PermissionHelper::UPDATE);
    }

    public function canDelete(string $menuCode): bool
    {
        return PermissionHelper::hasPermission($this->actionFor($menuCode), PermissionHelper::DELETE);
    }

    public function canApprove(string $menuCode): bool
    {
        return PermissionHelper::hasPermission($this->actionFor($menuCode), PermissionHelper::APPROVE);
    }
}
