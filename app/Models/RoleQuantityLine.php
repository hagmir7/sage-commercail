<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class RoleQuantityLine extends Pivot
{
    protected $fillable = ['quantity', 'role_id', 'line_id'];
}
