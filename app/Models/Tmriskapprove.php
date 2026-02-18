<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tmriskapprove extends TrBaseModel
{
    protected $table = 'tmriskapprove';
    protected $primaryKey = 'i_id_riskapprove';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $casts = [
        'i_id_riskapprove' => 'integer',
        'i_id_risk'        => 'integer',
        'i_id_role'        => 'integer',
        'i_entry'          => 'integer',
        'd_entry'          => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $model) {
            unset($model->attributes['i_update'], $model->attributes['d_update']);
        });
 }

    public function risk(): BelongsTo
    {
        return $this->belongsTo(Tmrisk::class, 'i_id_risk', 'i_id_risk');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Trrole::class, 'i_id_role', 'i_id_role');
    }
}
