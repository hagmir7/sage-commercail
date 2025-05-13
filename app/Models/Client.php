<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    protected $table = "F_COMPTET";
    protected $primaryKey = "CT_Num";
    protected $keyType = "stringe";
    public $incrementing = false;

    protected $dateFormat = 'Y-d-m H:i:s.v';


    public function docentete()
    {
        return $this->hasMany(Docentete::class, 'CT_NumPayeur', 'CT_Num');
    }

    public function remise(){
        return $this->hasMany(Remise::class, 'CT_Num', 'CT_Num');
    }
}
