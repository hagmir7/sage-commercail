<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuoteComparison extends Model
{
    protected $fillable = [
        'reference',
        'comparison_date',
        'department',
        'purchase_object',
        'selected_provider',
        'selection_justification',
        'purchasing_manager',
        'purchasing_manager_date',
        'general_director',
        'general_director_date',
        'status',
    ];

    protected $casts = [
        'comparison_date'        => 'date',
        'purchasing_manager_date' => 'date',
        'general_director_date'  => 'date',
    ];

    public function offers()
    {
        return $this->hasMany(QuoteOffer::class);
    }

    public function evaluations()
    {
        return $this->hasMany(QuoteEvaluation::class);
    }
}
