<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserLine extends Pivot
{
    protected $fillable = ['user_id', 'line_id', 'action_name'];
}
