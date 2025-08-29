<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = ['name', 'logo'];

    public function documents()
    {
        return $this->belongsToMany(company::class, 'document_companies');
    }

    public function movements(){
        return $this->hasMany(StockMovement::class);
    }
}



