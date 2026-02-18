<?php

namespace App\Models;

use App\Models\Concerns\HasEntryAudit;
use Illuminate\Database\Eloquent\Model;

class Trmenu extends Model
{
    use HasEntryAudit;

    protected $table = 'trmenu';

    protected $primaryKey = 'i_id_menu';
    public $incrementing = true;
    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'c_menu',
        'n_menu',
        'e_menu',
        'f_active',
        'i_entry', 'd_entry', 'i_update', 'd_update',
    ];

    protected $casts = [
        'i_id_menu' => 'int',
        'f_active' => 'bool',
        'i_entry' => 'int',
        'i_update' => 'int',
        'd_entry' => 'datetime',
        'd_update' => 'datetime',
    ];
}
