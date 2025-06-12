<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    protected $fillable = [
        'code_article',
        'designation',
        'emplacement_id',
        'emplacement_code',
        'inventory_id',
        'type',
        'quantity',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function emplacement()
    {
        return $this->belongsTo(Emplacement::class);
    }

    public function inventory(){
        return $this->belongsTo(Inventory::class);
    }

    public function article(){
        return $this->belongsTo(ArticleStock::class, 'code_article', 'code');
    }


}
