<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tmriskmitigation extends TrBaseModel
{
    protected $table = 'tmriskmitigation';
    protected $primaryKey = 'i_id_riskmitigation';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $casts = [
        'i_id_riskinherent' => 'integer',
        'v_mitigation_cost' => 'integer',
        'i_entry' => 'integer',
        'd_entry' => 'datetime',
        'i_update' => 'integer',
        'd_update' => 'datetime',
        'f_mitigation_month' => 'string',
    ];

    public function riskInherent(): BelongsTo
    {
        return $this->belongsTo(Tmriskinherent::class, 'i_id_riskinherent', 'i_id_riskinherent');
    }

    public function getMonthsAttribute(): array
    {
        $s = $this->attributes['f_mitigation_month'] ?? null;
        if (! is_string($s) || strlen($s) < 12) {
            return [];
        }

        $selected = [];
        for ($i = 1; $i <= 12; $i++) {
            if (isset($s[$i - 1]) && $s[$i - 1] === '1') {
                $selected[] = (string) $i;
            }
        }

        return $selected;
    }

    public function setMonthsAttribute($value): void
    {
        $arr = is_array($value) ? array_map('strval', $value) : [];
        $bits = '';
        for ($i = 1; $i <= 12; $i++) {
            $bits .= in_array((string) $i, $arr, true) ? '1' : '0';
        }
        $this->attributes['f_mitigation_month'] = $bits;
    }
}
