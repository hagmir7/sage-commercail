<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{


    protected $dateFormat = 'Y-d-m H:i:s.v';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $casts = [
        'abilities' => 'json',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

      public function setExpiresAtAttribute($date)
    {
        if ($date) {
            $this->attributes['expires_at'] = $date instanceof \DateTimeInterface
                ? $date->format('Y-d-m H:i:s.v')
                : Carbon::parse($date)->format('Y-d-m H:i:s.v');
        } else {
            $this->attributes['expires_at'] = null;
        }
    }

    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('Y-m-d H:i:s');
    }
}
