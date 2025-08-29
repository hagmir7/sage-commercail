<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyStock extends Model
{
    protected $fillable = ['code_article', 'designation', 'company_id', 'quantity', 'min_quantity'];


    public function article(){
        return $this->belongsTo(ArticleStock::class, 'code', 'code_article');
    }


    public function company(){
        return $this->belongsTo(Company::class);
    }
}
