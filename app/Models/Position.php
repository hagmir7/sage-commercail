<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    protected $fillable = ['code', 'depot_id', 'company_id'];

    public function depot()
    {
        return $this->belongsTo(Depot::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function palettes()
    {
        return $this->hasMany(Palette::class);
    }
}
