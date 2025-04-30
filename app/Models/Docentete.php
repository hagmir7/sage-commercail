<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Docentete extends Model
{
    protected $table = 'F_DOCENTETE';
    protected $primaryKey = 'cbMarq';
    protected $keyType = "integer";

    

    public $incrementing = false;

    protected $dateFormat = 'Y-d-m H:i:s.v';

    const CREATED_AT = 'cbCreation';
    const UPDATED_AT = 'cbModification';

    protected $guarded = [];


    protected function casts(): array
    {
        return [
            'DO_DateLivr' => 'datetime',
            'DO_DateLivrRealisee' => 'datetime',
            'DO_DateExpedition' => 'datetime'
        ];
    }


    public function doclignes(){
        return $this->hasMany(Docligne::class, 'DO_Piece', 'DO_Piece');
    }
}
