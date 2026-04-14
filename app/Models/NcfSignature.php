<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NcfSignature extends Model
{
    protected $fillable = ['non_conformity_id', 'entite', 'nom_prenom', 'date', 'visa'];

    protected $casts = [
        'date' => 'date',
    ];

    public function nonConformity(): BelongsTo
    {
        return $this->belongsTo(SupplierNonConformity::class, 'non_conformity_id');
    }
}