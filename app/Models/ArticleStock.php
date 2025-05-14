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
        'qte_inter',
        'qte_serie',
        'palette_id',
        'family_id',
        'thickness',
        'hieght',
        'width',
        'depth',
        'chant'
    ];

    public function palette()
    {
        return $this->belongsTo(Palette::class);
    }

    public function family()
    {
        return $this->belongsTo(ArticleFamily::class, 'family_id');
    }
}
