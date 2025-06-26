<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Inventory extends Model
{
    protected $fillable = ['name', 'date', 'status', 'description'];

    public function users()
    {
        return $this->belongsToMany(User::class, 'inventory_user');
    }

    public function movements()
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function palettes()
    {
        return $this->hasMany(Palette::class);
    }

    public function stock()
    {
        return $this->hasMany(InventoryStock::class);
    }



    protected static function booted()
    {
        static::deleted(function ($inventory) {
            DB::delete('
            DELETE inventory_article_palette
            FROM inventory_article_palette
            INNER JOIN inventory_stocks ON inventory_article_palette.inventory_stock_id = inventory_stocks.id
            WHERE inventory_stocks.inventory_id = ?
        ', [$inventory->id]);
            DB::delete('DELETE FROM inventory_movements WHERE inventory_id = ?', [$inventory->id]);
            DB::delete('DELETE FROM inventory_stocks WHERE inventory_id = ?', [$inventory->id]);
            DB::delete('DELETE FROM inventory_user WHERE inventory_id = ?', [$inventory->id]);
        });
    }
}
