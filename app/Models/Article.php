<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    protected $table = "F_ARTICLE";
    protected $primaryKey = "AR_Ref";
    protected $keyType = "string";
    public $incrementing = false;


    protected $dateFormat = 'Y-d-m H:i:s.v';

    const CREATED_AT = 'cbCreation';
    const UPDATED_AT = 'cbModification';

    protected $guarded = [];
}
