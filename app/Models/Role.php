<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $fillable = ['name', 'guard_name'];

    // protected $dateFormat = 'Y-d-m H:i:s.v';
}
