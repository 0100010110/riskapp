<?php

namespace App\Services;

use App\Helper\PermissionHelper;
use App\Models\Trrole;
use App\Models\Trrolemenu;
use App\Models\Truserrole;
use Illuminate\Support\Facades\Auth;

class PermissionService
{
    /**
     * Cek akses menu berdasarkan c_menu + bitmask action.
     */
    public function can(string $menuCode, int $actionBit): bool
    {
        $user = Auth::user();
        if (!$user) {
            return false;
        }

        $roleId = Truserrole::query()
            ->where('i_id_user', $user->id)
            ->value('i_id_role');

        if (!$roleId) {
            return false;
        }

        $roleCode = Trrole::query()->where('i_id_role', $roleId)->value('c_role');
        if ($roleCode === 'SADM') {
            return true;
        }

        return Trrolemenu::query()
            ->join('trmenu', 'trmenu.i_id_menu', '=', 'trrolemenu.i_id_menu')
            ->where('trrolemenu.i_id_role', $roleId)
            ->where('trmenu.c_menu', $menuCode)
            ->where('trrolemenu.f_active', true)
            ->whereRaw('(trrolemenu.c_action & ?) = ?', [$actionBit, $actionBit])
            ->exists();
    }

    public function canRead(string $menuCode): bool
    {
        return $this->can($menuCode, PermissionHelper::READ);
    }
}
