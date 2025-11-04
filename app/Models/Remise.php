<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Remise extends Model
{
    protected $table = "F_FAMCLIENT";
    protected $primaryKey = "cbMarq";
    protected $keyType = "integer";
    public $incrementing = true;

    
    protected $dateFormat = 'Y-d-m H:i:s.v';

    const CREATED_AT = 'cbCreation';
    const UPDATED_AT = 'cbModification';

    protected $guarded = [];


}
