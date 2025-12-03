<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaRequest extends Model
{
    protected $fillable = [
        'phone',
        'step',
        'answers',
    ];

    protected $casts = [
        'answers' => 'array',
    ];
}
