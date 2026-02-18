<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Tmtaxonomyscale extends TrBaseModel
{
    protected $table = 'tmtaxonomyscale';
    protected $primaryKey = 'i_id_taxonomyscale';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $casts = [
        'i_id_taxonomyscale' => 'integer',
        'i_id_taxonomy'      => 'integer',
        'i_id_scale'         => 'integer',
        'i_entry'            => 'integer',
        'i_update'           => 'integer',
        'd_entry'            => 'datetime',
        'd_update'           => 'datetime',
    ];

    protected $fillable = [
        'i_id_taxonomy',
        'i_id_scale',
    ];

    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(Tmtaxonomy::class, 'i_id_taxonomy', 'i_id_taxonomy');
    }

    public function scale(): BelongsTo
    {
        return $this->belongsTo(Trscale::class, 'i_id_scale', 'i_id_scale');
    }
}
