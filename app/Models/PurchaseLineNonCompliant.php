<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseLineNonCompliant extends Model
{

    protected $table = 'purchase_line_non_compliant';

    protected $fillable = [
        'purchase_line_id',
        'quantity',
        'user_id',
        'note',
        'file',
        'supplier_code',
        'status'
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
