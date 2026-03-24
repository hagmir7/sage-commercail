<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OfLine extends Model
{
    protected $table = 'of_lines';

    protected $fillable = [
        'of_id',
        'article_stock_id',
        'article_code',
        'quantity',
        'quantity_produite',
        'statut',
    ];

    protected $casts = [
        'quantity'         => 'decimal:2',
        'quantity_produite' => 'decimal:2',
    ];

    public function of()
    {
        return $this->belongsTo(Of::class, 'of_id');
    }

    public function article()
    {
        return $this->belongsTo(ArticleStock::class, 'article_stock_id', 'id');
    }
}