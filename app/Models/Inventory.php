<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $fillable = ['name', 'date', 'status', 'description'];

    public function users(){
        return $this->belongsToMany(User::class, 'inventory_user');
    }

}
