<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArticleStock extends Model
{

    protected $fillable = [
        'code',
        'description',
        'name',
        'color',
        'article_id',
        'qr_code',
        'quantity',
        'qte_inter',
        'stock_min',
        'qte_serie',
        'condition',
        'family_id',
        'thickness',
        'height',
        'width',
        'price',
        'depth',
        'chant',
        'palette_condition',
        'unit',
        'gamme'
    ];

    public function article()
    {
        return $this->belongsTo(Article::class, 'cbMarq');
    }

    public function family()
    {
        return $this->belongsTo(ArticleFamily::class, 'cbMarq');
    }

    public function palettes()
    {
        return $this->belongsToMany(Palette::class)->withPivot('quantity')->withTimestamps();
    }

    public function inventoryMovements(){
        return $this->hasMany(InventoryMovement::class, 'code_article', 'code');
    }
}
