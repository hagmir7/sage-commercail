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
        'qte_inter',
        'qte_serie',
        'family_id',
        'thickness',
        'height',
        'width',
        'depth',
        'chant'
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
}
