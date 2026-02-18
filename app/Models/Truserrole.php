<?php

namespace App\Models;

class Truserrole extends TrBaseModel
{
    protected $table = 'truserrole';
    protected $primaryKey = 'i_id_userrole';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $casts = [
        'i_id_userrole' => 'integer',
        'i_id_user'     => 'integer',
        'i_id_role'     => 'integer',
        'i_entry'       => 'integer',
        'i_update'      => 'integer',
        'd_entry'       => 'datetime',
        'd_update'      => 'datetime',
    ];

    protected $fillable = [
        'i_id_user',
        'i_id_role',
    ];

    public function role()
    {
        return $this->belongsTo(Trrole::class, 'i_id_role', 'i_id_role');
    }
}
