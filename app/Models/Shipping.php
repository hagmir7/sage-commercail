<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipping extends Model
{
    protected $fillable = ['code', 'shipping_date', 'document_id', 'user_id', 'validation_date'];


    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function criteria()
    {
        return $this->hasMany(ShippingCriteriaValue::class, 'shipping_id');
    }
}
