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
}
