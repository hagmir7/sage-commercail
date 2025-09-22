<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrentPiece extends Model
{
    protected $table = 'F_DOCCURRENTPIECE';
    protected $primaryKey = 'DC_IdCol';
    public $timestamps = false;
}
