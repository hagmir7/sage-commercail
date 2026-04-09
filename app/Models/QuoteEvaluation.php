<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuoteEvaluation extends Model
{
    protected $fillable = [
        'quote_comparison_id',
        'provider_name',
        'price_score',
        'delivery_score',
        'technical_score',
        'reliability_score',
        'payment_score',
        'weighted_total',
    ];

    const WEIGHTS = [
        'price_score'       => 30,
        'delivery_score'    => 25,
        'technical_score'   => 25,
        'reliability_score' => 10,
        'payment_score'     => 10,
    ];

    public function comparison()
    {
        return $this->belongsTo(QuoteComparison::class, 'quote_comparison_id');
    }

    protected static function booted(): void
    {
        static::saving(function (self $e) {
            $total = 0;
            foreach (self::WEIGHTS as $field => $weight) {
                $total += ($e->$field * $weight) / 100;
            }
            $e->weighted_total = round($total, 2);
        });
    }
}
