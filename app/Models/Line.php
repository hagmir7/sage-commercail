<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Line extends Model
{
    protected $fillable = [
        'ref',
        'quantity',
        'design',
        'dimensions',
        'document_id',
        'docligne_id',
        'company_id',
        'role_id',
        'complated'
    ];


    protected $dateFormat = 'Y-d-m H:i:s.v';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function docligne()
    {
        return $this->belongsTo(Docligne::class, 'docligne_id', 'cbMarq');
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
