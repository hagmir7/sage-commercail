<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleSupplier extends Model
{
    protected $table = "F_ARTFOURNISS";
    


    protected $guarded = [];

    public function articles()
    {
        return $this->hasMany(Article::class, 'AR_Ref', 'AR_Ref');
    }
}
