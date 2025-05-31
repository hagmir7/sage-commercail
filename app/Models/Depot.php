<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Depot extends Model
{
    protected $fillable = ['code', 'company_id'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function emplacements()
    {
        return $this->hasMany(Emplacement::class);
    }
}
