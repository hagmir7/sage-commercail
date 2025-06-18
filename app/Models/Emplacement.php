<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Emplacement extends Model
{
    protected $fillable = ['depot_id', 'code', 'description'];


    public function articles()
    {
        return $this->belongsToMany(ArticleStock::class, 'article_emplacement', 'emplacement_id', 'article_stock_id')
            ->withPivot('quantity')
            ->withTimestamps();
    }


    public function depot()
    {
        return $this->belongsTo(Depot::class, 'depot_id');
    }


    public function palettes()
    {
        return $this->hasMany(Palette::class);
    }
}
