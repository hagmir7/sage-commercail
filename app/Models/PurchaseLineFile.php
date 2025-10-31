<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseLineFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_line_id',
        'file_path',
        'file_name',
        'user_id',
    ];

    // related line
    public function line()
    {
        return $this->belongsTo(PurchaseLine::class, 'purchase_line_id');
    }

    // uploader user
    public function uploader()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

     protected static function booted()
    {
        static::creating(function ($record) {
            $record->user_id = auth()->id();
        });
    }
}
