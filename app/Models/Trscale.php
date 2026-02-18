<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Trscale extends TrBaseModel
{
    protected $table = 'trscale';
    protected $primaryKey = 'i_id_scale';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $casts = [
        'i_id_scale'         => 'integer',
        'c_scale_type'       => 'string',
        'f_scale_finance'    => 'boolean',
        'i_scale'            => 'integer',
        'n_scale_assumption' => 'string',
        'v_scale'            => 'string',
        'i_entry'            => 'integer',
        'i_update'           => 'integer',
        'd_entry'            => 'datetime',
        'd_update'           => 'datetime',
    ];

    protected $fillable = [
        'c_scale_type',
        'f_scale_finance',
        'i_scale',
        'n_scale_assumption',
        'v_scale',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(Trscaledetail::class, 'i_id_scale', 'i_id_scale');
    }
}
