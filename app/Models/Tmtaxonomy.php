<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tmtaxonomy extends TrBaseModel
{
    protected $table = 'tmtaxonomy';
    protected $primaryKey = 'i_id_taxonomy';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $casts = [
        'i_id_taxonomyparent' => 'integer',
        'c_taxonomy_level' => 'integer',
        'i_entry' => 'integer',
        'd_entry' => 'datetime',
        'i_update' => 'integer',
        'd_update' => 'datetime',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'i_id_taxonomyparent', 'i_id_taxonomy');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'i_id_taxonomyparent', 'i_id_taxonomy');
    }

    public function taxonomyScales(): HasMany
    {
        return $this->hasMany(Tmtaxonomyscale::class, 'i_id_taxonomy', 'i_id_taxonomy');
    }

    public function scales(): BelongsToMany
    {
        return $this->belongsToMany(
            Trscale::class,
            'tmtaxonomyscale',
            'i_id_taxonomy',
            'i_id_scale',
            'i_id_taxonomy',
            'i_id_scale',
        );
    }

    public function rootTaxonomy(): ?self
    {
        $node = $this;

        $guard = 0;
        while ($node->parent && $guard < 10) {
            $node = $node->parent;
            $guard++;
        }

        return $node ?: null;
    }

    public function getRootTaxonomyIdAttribute(): ?int
    {
        return $this->rootTaxonomy()?->i_id_taxonomy;
    }
}
