<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryStock extends Model
{
    protected $fillable = [
        'code_article',
        'designation',
        'inventory_id',
        'stock_min',
        'stock_init',
        'stock_end',
        'condition',
        'quantity',
        'price',

    ];
}
