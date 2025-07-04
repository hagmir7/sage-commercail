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
        'qr_code',
        'quantity',
        'qte_inter',
        'stock_min',
        'qte_serie',
        'condition',
        'thickness',
        'height',
        'width',
        'price',
        'depth',
        'chant',
        'palette_condition',
        'unit',
        'gamme',
        'code_supplier',
        'code_supplier_2',
        'category'
    ];


    public function palettes()
    {
        return $this->belongsToMany(Palette::class)->withPivot('quantity')->withTimestamps();
    }

    public function companies(){
        return $this->belongsToMany(Company::class, 'article_company');
    }

    public function inventoryMovements(){
        return $this->hasMany(InventoryMovement::class, 'code_article', 'code');
    }
}
