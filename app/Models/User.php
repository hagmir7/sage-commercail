<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'full_name',
        'email',
        'password',
        'phone',
        'company_id',
        'service_id',
        'is_active'
    ];

    // protected $dateFormat = 'Y-d-m H:i:s.v';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }


    public function movements(){
        return $this->hasMany(StockMovement::class, 'moved_by');
    }

    public function controlles(){
        return $this->hasMany(Palette::class, 'controlled_by');
    }

    public function validations(){
        return $this->hasMany(Document::class, 'validated_by');
    }

    public function chargements(){
        return $this->hasMany(Palette::class, 'delivered_by');
    }

     public function preparations(){
        return $this->hasMany(Palette::class, 'user_id');
    }

    public function lines()
    {
        return $this->belongsToMany(Line::class, 'user_line')
            ->withPivot('action_name')
            ->withTimestamps();
    }
}
