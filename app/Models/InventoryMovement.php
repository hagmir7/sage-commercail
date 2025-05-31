<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryMovement extends Model
{
    protected $fillable = [
        'code_article',
        'designation',
        'emplacement_id',
        'type',
        'quantity',
        'user_id',
        'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function emplacement()
    {
        return $this->belongsTo(Emplacement::class);
    }
}
