<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Line extends Model
{
    protected $fillable = ['tiers', 'ref', 'quantity', 'design', 'dimensions', 'document_id', 'docligne_id', 'company_id', 'role_id', 'complated'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function docligne()
    {
        return $this->belongsTo(Docligne::class);
    }

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
