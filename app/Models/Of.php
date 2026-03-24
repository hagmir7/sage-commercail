<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Of extends Model
{
    protected $table = 'ofs';

    protected $fillable = [
        'reference',
        'date_lancement',
        'date_demarrage',
        'reference_machine',
        'type_commande',
        'statut',
        'user_id',
    ];

    protected $casts = [
        'date_lancement' => 'date',
        'date_demarrage' => 'date',
    ];

    public function lines()
    {
        return $this->hasMany(OfLine::class, 'of_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}