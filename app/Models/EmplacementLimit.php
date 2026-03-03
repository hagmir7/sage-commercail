<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class EmplacementLimit extends Pivot
{
    protected $fillable = ["quantity", "emplacement_id", "article_stock_id"];
}
