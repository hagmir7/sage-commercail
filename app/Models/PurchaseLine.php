<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_document_id',
        'code',
        'description',
        'quantity',
        'unit',
        'estimated_price',
        'total',
    ];


    public function document()
    {
        return $this->belongsTo(PurchaseDocument::class, 'purchase_document_id');
    }


    public function files()
    {
        return $this->hasMany(PurchaseLineFile::class);
    }

     protected static function booted()
    {
        static::saving(function ($line) {
            if (!is_null($line->quantity) && !is_null($line->estimated_price)) {
                $line->total = $line->quantity * $line->estimated_price;
            }
        });
    }
}
