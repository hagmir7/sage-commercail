<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Palette extends Model
{
    protected $fillable = [
        'code',
        'company_id',
        'emplacement_id',
        'document_id',
        'type',
        'user_id',
        'controlled',
        'delivered_at',
        'delivered_by',
        'inventory_id'
    ];

    // public $timestamps = false;

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
        return $this->belongsToMany(ArticleStock::class, 'article_palette')->withPivot('quantity')->withTimestamps();
    }

    public function inventoryArticles(){
        return $this->belongsToMany(InventoryStock::class, 'inventory_article_palette')->withPivot('quantity')->withTimestamps();
    }

    public function document(){
        return $this->belongsTo(Document::class);
    }


    public function lines()
    {
        return $this->belongsToMany(Line::class, 'line_palettes')->withPivot(['quantity', 'controlled_at']);
    }

    public function user(){
        return $this->belongsTo(User::class);
    }

    
}
