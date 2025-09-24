<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Compte extends Model
{
    // Explicit table name
    protected $table = 'F_COMPTET';

    protected $primaryKey = 'CT_Num';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'CT_Num',
        'CT_Intitule',
        'CT_Type',
        'CT_Adresse',
        'CT_CodePostal',
        'CT_Ville',
        'CT_Pays',
        'CT_Telephone',
        'CT_EMail',
        'CT_Site',
    ];
}
