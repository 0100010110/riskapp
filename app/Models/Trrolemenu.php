<?php

namespace App\Models;

use Illuminate\Support\Facades\Cache;

class Trrolemenu extends TrBaseModel
{

    
    protected static function booted(): void
    {
        $forget = function (int $roleId, int $menuId): void {
            if ($roleId <= 0 || $menuId <= 0) {
                return;
            }

            $menuCode = Trmenu::query()
                ->where('i_id_menu', $menuId)
                ->value('c_menu');

            if (! $menuCode) {
                return;
            }

            $menuCode = strtolower(trim((string) $menuCode));

            $userIds = Truserrole::query()
                ->where('i_id_role', $roleId)
                ->pluck('i_id_user')
                ->map(fn ($v) => (int) $v)
                ->filter(fn ($v) => $v > 0)
                ->unique()
                ->all();

            foreach ($userIds as $userId) {
                Cache::forget("perm:{$userId}:{$menuCode}");
            }
        };

        static::saved(function (self $model) use ($forget): void {
            $forget((int) $model->i_id_role, (int) $model->i_id_menu);

            $origRoleId = (int) $model->getOriginal('i_id_role');
            $origMenuId = (int) $model->getOriginal('i_id_menu');

            if (
                $origRoleId > 0
                && $origMenuId > 0
                && ($origRoleId !== (int) $model->i_id_role || $origMenuId !== (int) $model->i_id_menu)
            ) {
                $forget($origRoleId, $origMenuId);
            }
        });

        static::deleted(function (self $model) use ($forget): void {
            $forget((int) $model->i_id_role, (int) $model->i_id_menu);
        });
    }
    protected $table = 'trrolemenu';
    protected $primaryKey = 'i_id_rolemenu';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $casts = [
        'i_id_rolemenu' => 'integer',
        'i_id_role'     => 'integer',
        'i_id_menu'     => 'integer',
        'c_action'      => 'integer',
        'f_active'      => 'boolean',
        'i_entry'       => 'integer',
        'i_update'      => 'integer',
        'd_entry'       => 'datetime',
        'd_update'      => 'datetime',
    ];

    protected $fillable = [
        'i_id_role',
        'i_id_menu',
        'c_action',
        'f_active',
    ];

    public function role()
    {
        return $this->belongsTo(Trrole::class, 'i_id_role', 'i_id_role');
    }

    public function menu()
    {
        return $this->belongsTo(Trmenu::class, 'i_id_menu', 'i_id_menu');
    }
}
