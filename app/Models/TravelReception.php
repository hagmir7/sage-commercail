<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelReception extends Model
{
    protected $fillable = ['travel_driver_id', 'code', 'company_id'];


    public function driver()
    {
        return $this->belongsTo(TravelDriver::class, 'travel_driver_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
