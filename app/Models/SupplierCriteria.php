<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierCriteria extends Model
{


    public function interviews()
    {
        return $this->belongsToMany(
            SupplierInterview::class,
            'supplier_interview_criterias'
        )->withPivot('note')->withTimestamps();
    }
}
