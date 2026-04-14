<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NcfAttachment extends Model
{
    protected $fillable = ['non_conformity_id', 'filename', 'path', 'mime_type', 'size'];

    public function nonConformity(): BelongsTo
    {
        return $this->belongsTo(SupplierNonConformity::class, 'non_conformity_id');
    }
}