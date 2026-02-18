<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trscaledetail extends TrBaseModel
{
    public const OPERATORS = [
        1 => '>',
        2 => '>=',
        3 => '=',
        4 => '<=',
        5 => '<',
    ];

    protected $table = 'trscaledetail';
    protected $primaryKey = 'i_id_scaledetail';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $casts = [
        'i_id_scaledetail' => 'integer',
        'i_id_scale'       => 'integer',
        'i_detail_score'   => 'integer',
        'v_detail'         => 'integer',
        'c_detail'         => 'integer',
        'f_active'         => 'boolean',
        'i_entry'          => 'integer',
        'i_update'         => 'integer',
        'd_entry'          => 'datetime',
        'd_update'         => 'datetime',
    ];

    protected $fillable = [
        'i_id_scale',
        'i_detail_score',
        'v_detail',
        'c_detail',
        'f_active',
    ];

    public function scale(): BelongsTo
    {
        return $this->belongsTo(Trscale::class, 'i_id_scale', 'i_id_scale');
    }

    public function getOperatorLabelAttribute(): string
    {
        $key = (int) ($this->v_detail ?? 0);

        return self::OPERATORS[$key] ?? (string) $this->v_detail;
    }

    public function mapsAsA(): HasMany
    {
        return $this->hasMany(Trscalemap::class, 'i_id_scale_a', 'i_id_scaledetail');
    }

    public function mapsAsB(): HasMany
    {
        return $this->hasMany(Trscalemap::class, 'i_id_scale_b', 'i_id_scaledetail');
    }
}
