<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Docligne extends Model
{
    protected $table = "F_DOCLIGNE";
    protected $primaryKey = "cbMarq";
    protected $keyType = "string"; 
    public $incrementing = false; 

    protected $guarded = [];

    // protected $dateFormat = 'Y-d-m H:i:s.v';

    // const CREATED_AT = 'cbCreation';
    // const UPDATED_AT = 'cbModification';

    public $timestamps = false;




    public function docentete()
    {
        return $this->belongsTo(Docentete::class, "DO_Piece", 'DO_Piece');
    }


    public function article()
    {
        return $this->belongsTo(Article::class, 'AR_Ref', 'AR_Ref'); 
    }

    public function line()
    {
        return $this->hasOne(Line::class, 'docligne_id', 'cbMarq');
    }

    public function stock()
    {
        return $this->belongsTo(ArticleStock::class, 'AR_Ref', 'code');
    }
}
