<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleFamily extends Model
{

    protected $table = "F_FAMILLE";
    protected $primaryKey = "FA_CodeFamille";
    protected $keyType = "string";


    protected $guarded = [];

    public function articles()
    {
        return $this->hasMany(Article::class, 'FA_CodeFamille', 'FA_CodeFamille');
    }
}
