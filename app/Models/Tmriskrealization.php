<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tmriskrealization extends TrBaseModel
{
    protected $table = 'tmriskrealization';
    protected $primaryKey = 'i_id_riskrealization';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $casts = [
        'i_id_riskinherent'   => 'integer',
        'p_risk_realization'  => 'integer',
        'v_realization_cost'  => 'integer',
        'i_id_scalemap'       => 'integer',
        'v_exposure'          => 'integer',
        'i_entry'             => 'integer',
        'd_entry'             => 'datetime',
        'i_update'            => 'integer',
        'd_update'            => 'datetime',
    ];

    public function riskInherent(): BelongsTo
    {
        return $this->belongsTo(Tmriskinherent::class, 'i_id_riskinherent', 'i_id_riskinherent');
    }

    public function scaleMap(): BelongsTo
    {
        return $this->belongsTo(Trscalemap::class, 'i_id_scalemap', 'i_id_scalemap');
    }
}
