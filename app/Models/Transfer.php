<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transfer extends Model
{
    protected $fillable = ['user_id', 'palette_id', 'from_company', 'to_company', 'transfer_by'];


    public function form_company()
    {
        return $this->belongsTo(Company::class, 'from_company');
    }


    public function to_company()
    {
        return $this->belongsTo(Company::class, 'to_company');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function palette()
    {
        return $this->belongsTo(Palette::class);
    }

    public function transfer_by()
    {
        return $this->belongsTo(User::class, 'transfer_by');
    }

}
