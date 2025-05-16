<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Palette extends Model
{
    protected $fillable = ['code', 'company_id', 'position_id', 'document_id', 'type', 'user_id'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function articles()
    {
        return $this->hasMany(Article::class);
    }
}
