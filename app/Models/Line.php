<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Line extends Model
{
    protected $fillable = [
        'ref',
        'quantity',
        'name',
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
        'status_id',
        'first_company_id',
        'quantity_prepare',
        'company_code'
    ];


    // protected $dateFormat = 'Y-d-m H:i:s.v';

    // const CREATED_AT = 'created_at';
    // const UPDATED_AT = 'updated_at';

    public $timestamps = false;

    public function company()
    {
        return $this->belongsTo(Company::class);
    }



    public function first_company()
    {
        return $this->belongsTo(Company::class, 'first_company');
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

    public function user_role(){
        return $this->belongsTo(User::class, 'role_id', 'id');
    }


    public function palettes(){
        return $this->belongsToMany(Palette::class, 'line_palettes')->withPivot(['quantity', 'controlled_at']);
    }

    public function status(){
        return $this->belongsTo(Status::class);
    }


    public function roleQuantity()
    {
        $user = auth()->user();

        // If the user has the "controller" role, return all
        if ($user && $user->roles->contains('name', 'controller')) {
            return $this->belongsToMany(Role::class, 'role_quantity_line')
                ->withPivot('quantity');
        }

        // Otherwise filter by user's roles
        $roleIds = $user ? $user->roles->pluck('id')->toArray() : [];

        return $this->belongsToMany(Role::class, 'role_quantity_line')
            ->withPivot('quantity')
            ->whereIn('role_id', $roleIds);
    }
}
