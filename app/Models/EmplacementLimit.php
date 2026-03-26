<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class EmplacementLimit extends Pivot
{
    protected $table = 'emplacement_limit';

    protected $fillable = [
        "quantity",
        "emplacement_id",
        "article_stock_id"
    ];

    public function emplacement()
    {
        return $this->belongsTo(Emplacement::class, 'emplacement_id');
    }

    public function article()
    {
        return $this->belongsTo(ArticleStock::class, 'article_stock_id');
    }
}