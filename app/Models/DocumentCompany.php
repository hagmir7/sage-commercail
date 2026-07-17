<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentCompany extends Model
{

    protected $table = 'document_companies';

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function fabricatedBy()
    {
        return $this->belongsTo(User::class, 'fabricated_by');
    }

    public function validatedBy()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function controlledBy()
    {
        return $this->belongsTo(User::class, 'controlled_by');
    }
}
