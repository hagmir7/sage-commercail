<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Emplacement extends Model
{
    protected $fillable = ['depot_id', 'code', 'description', 'inventory_id'];

    public function articles()
    {
        return $this->belongsToMany(
            ArticleStock::class,         // Related model
            'article_emplacement',       // Pivot table
            'emplacement_id',            // Foreign key on pivot for this model
            'article_stock_id'           // Foreign key on pivot for the other model
        )
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
