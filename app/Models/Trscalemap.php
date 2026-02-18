<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trscalemap extends TrBaseModel
{
    protected $table = 'trscalemap';
    protected $primaryKey = 'i_id_scalemap';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $casts = [
        'i_id_scalemap' => 'integer',
        'i_id_scale_a'  => 'integer',
        'i_id_scale_b'  => 'integer',
        'i_map'         => 'integer',
        'n_map'         => 'string',
        'c_map'         => 'string',
        'i_entry'       => 'integer',
        'i_update'      => 'integer',
        'd_entry'       => 'datetime',
        'd_update'      => 'datetime',
    ];

    protected $fillable = [
        'i_id_scale_a',
        'i_id_scale_b',
        'i_map',
        'n_map',
        'c_map',
    ];

    public function scaleDetailA(): BelongsTo
    {
        return $this->belongsTo(Trscaledetail::class, 'i_id_scale_a', 'i_id_scaledetail');
    }

    public function scaleDetailB(): BelongsTo
    {
        return $this->belongsTo(Trscaledetail::class, 'i_id_scale_b', 'i_id_scaledetail');
    }
}
