<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Docligne extends Model
{
    protected $table = "F_DOCLIGNE";
    protected $primaryKey = "cbMarq";
    protected $keyType = "integer";
    public $incrementing = true;

    protected $guarded = [];

    protected $dateFormat = 'Y-d-m H:i:s.v';

    const CREATED_AT = 'cbCreation';
    const UPDATED_AT = 'cbModification';


    public function docentete()
    {
        return $this->belongsTo(Docentete::class, "DC_Piece", "cbMarq");
    }
}
