<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class ArticlePalette extends Pivot
{
    public function article()
    {
        return $this->belongsTo(ArticleStock::class, 'article_stock_id');
    }

    public function palette()
    {
        return $this->belongsTo(Palette::class);
    }
}
