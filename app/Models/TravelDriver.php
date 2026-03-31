<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelDriver extends Model
{
    protected $fillable = ['full_name', 'cin', 'code'];


    public function travels()
    {
        return $this->hasMany(TravelReception::class);
    }
}
