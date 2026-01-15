<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentReception extends Model
{
    protected $fillable = [
        'emplacement_code',
        'article_code',
        'quantity',
        'document_id',
        'username',
        'company',
        'colis_type',
        'colis_quantity'
    ];

    public $timestamps = false;

    // Add this relationship
    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function article()
    {
        return $this->belongsTo(ArticleStock::class, 'article_code', 'code');
    }

    public function emplacement()
    {
        return $this->belongsTo(Emplacement::class, 'emplacement_code', 'code');
    }
}