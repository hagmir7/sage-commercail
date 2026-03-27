<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelReception extends Model
{
    protected $fillable = ['travel_driver_id', 'code', 'company_id'];
}
