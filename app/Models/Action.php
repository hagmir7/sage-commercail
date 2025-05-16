<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Action extends Model
{
    protected $fillable = ['user_id', 'action_type', 'line_id', 'description', 'start','end'];

}
