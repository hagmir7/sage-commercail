<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'article_id',
        'designation',
        'from_emplacement_id',
        'to_emplacement_id',
        'emplacement_id',
        'to_palette_id',
        'article_stock_id',
        'quantity',
        'movement_type',
        'movement_date',
        'moved_by',
        'note',
        'code_article',
        'company_id'
    ];


    public function article()
    {
        return $this->belongsTo(ArticleStock::class);
    }

    public function fromEmplacement()
    {
        return $this->belongsTo(Emplacement::class, 'from_emplacement_id');
    }

    public function toEmplacement()
    {
        return $this->belongsTo(Emplacement::class, 'to_emplacement_id');
    }

    public function movedBy(){
        return $this->belongsTo(User::class, 'moved_by');
    }
}
