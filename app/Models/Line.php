<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Line extends Model
{
    protected $fillable = [
        'ref',
        'quantity',
        'design',
        'dimensions',
        'document_id',
        'docligne_id',
        'company_id',
        'role_id',
        'next_role_id',
        'complated',
        'palette_id',
        'received',
        'complation_date',
        'validated',
        'status_id'
    ];


    // protected $dateFormat = 'Y-d-m H:i:s.v';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function docligne()
    {
        return $this->belongsTo(Docligne::class, 'docligne_id', 'cbMarq');
    }


    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function article_stock(){
        return $this->belongsTo(ArticleStock::class, 'ref', 'code');
    }


    public function palettes(){
        return $this->belongsToMany(Palette::class, 'line_palettes')->withPivot(['quantity', 'controlled_at']);
    }

    public function status(){
        return $this->belongsTo(Status::class);
    }

}
