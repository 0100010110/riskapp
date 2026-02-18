<?php

namespace App\Models;

class Trrole extends TrBaseModel
{
    protected $table = 'trrole';
    protected $primaryKey = 'i_id_role';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $casts = [
        'i_id_role' => 'integer',
        'f_active'  => 'boolean',
        'i_entry'   => 'integer',
        'i_update'  => 'integer',
        'd_entry'   => 'datetime',
        'd_update'  => 'datetime',
    ];

    protected $fillable = [
        'c_role',
        'n_role',
        'e_role',
        'f_active',
    ];
}
