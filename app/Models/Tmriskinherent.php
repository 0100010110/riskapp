<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tmriskinherent extends TrBaseModel
{
    protected $table = 'tmriskinherent';
    protected $primaryKey = 'i_id_riskinherent';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $casts = [
        'i_id_risk' => 'integer',
        'i_id_scalemap' => 'integer',
        'v_exposure' => 'integer',
        'i_id_scalemapres' => 'integer',
        'v_exposure_res' => 'integer',
        'i_entry' => 'integer',
        'd_entry' => 'datetime',
        'i_update' => 'integer',
        'd_update' => 'datetime',
    ];

    public function risk(): BelongsTo
    {
        return $this->belongsTo(Tmrisk::class, 'i_id_risk', 'i_id_risk');
    }

    public function scaleMapInherent(): BelongsTo
    {
        return $this->belongsTo(Trscalemap::class, 'i_id_scalemap', 'i_id_scalemap');
    }

    public function scaleMapResidual(): BelongsTo
    {
        return $this->belongsTo(Trscalemap::class, 'i_id_scalemapres', 'i_id_scalemap');
    }
}
