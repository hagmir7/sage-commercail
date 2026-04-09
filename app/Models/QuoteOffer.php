<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuoteOffer extends Model
{
    protected $fillable = [
        'quote_comparison_id', 'provider_name', 'quote_reference', 'quote_date',
        'validity_period', 'product_designation', 'quantity', 'unit_price',
        'total_price', 'payment_conditions', 'delivery_delay', 'warranty',
        'technical_compliance', 'observations',
    ];

    protected $casts = ['quote_date' => 'date'];

    public function comparison()
    {
        return $this->belongsTo(QuoteComparison::class, 'quote_comparison_id');
    }

}