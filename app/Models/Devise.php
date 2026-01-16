<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Devise extends Model
{
    protected $table = "P_DEVISE";
    protected $primaryKey = "cbIndice";
    protected $keyType = "integer";


    protected $guarded = [];
}
