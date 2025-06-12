<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $fillable = ['name', 'date', 'status', 'description'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'inventory_user');
    }

    public function movements(){
        return $this->hasMany(InventoryMovement::class);
    }

    public function stock(){
        return $this->hasMany(InventoryStock::class);
    }

}
