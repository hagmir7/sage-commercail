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
        'category',
        'company_code'
    ];


    public function palettes()
    {
        return $this->belongsToMany(Palette::class, 'article_palette')->withPivot('quantity')->withTimestamps();
    }

    public function companies(){
        return $this->belongsToMany(Company::class, 'article_company');
    }

    public function emplacements()
    {
        return $this->belongsToMany(
            Emplacement::class,          // Related model
            'article_emplacement',       // Pivot table
            'article_stock_id',          // Foreign key on pivot for this model
            'emplacement_id'             // Foreign key on pivot for the other model
        )
            ->withPivot('quantity')
            ->withTimestamps();
    }



    public function inventoryMovements(){
        return $this->hasMany(InventoryMovement::class, 'code_article', 'code');
    }
}
