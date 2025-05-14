<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleFamily extends Model
{
    protected $fillable = ['code', 'description'];

    public function articles()
    {
        return $this->hasMany(Article::class, 'family_id');
    }
}
