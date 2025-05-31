<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Emplacement extends Model
{
    protected $fillable = ['depot_id', 'code', 'description'];


    public function articles()
    {
        return $this->belongsToMany(ArticleStock::class, 'article_emplacement')->withPivot('quantity')->withTimestamps();
    }

    public function depte(){
        return $this->belongsTo(Depot::class);
    }
}
