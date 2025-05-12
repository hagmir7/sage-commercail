<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Docligne extends Model
{
    protected $table = "F_DOCLIGNE";
    protected $primaryKey = "DO_Piece";
    protected $keyType = "string"; // <- was integer
    public $incrementing = false;  // <- must be false for non-numeric PKs

    protected $guarded = [];

    protected $dateFormat = 'Y-d-m H:i:s.v';

    const CREATED_AT = 'cbCreation';
    const UPDATED_AT = 'cbModification';

    public function docentete()
    {
        return $this->belongsTo(Docentete::class, "DO_Piece");
    }

    public function article()
    {
        return $this->belongsTo(Article::class, "AR_Ref");
    }
}
