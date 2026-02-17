<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierInterview extends Model
{

    protected $fillable = [
        'CT_Num',
        'date',
        'description',
        'user_id',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class, 'CT_Num');
    }


    public function criterias()
    {
        return $this->belongsToMany(
            SupplierCriteria::class,
            'supplier_interview_criterias', 
            'supplier_interview_id',
            'supplier_criteria_id'
        )->withPivot('note');
    }
}
