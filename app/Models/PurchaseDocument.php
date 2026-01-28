<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseDocument extends Model
{
    
    protected $fillable = [
        'code',
        'user_id',
        'reference',
        'service_id',
        'status',
        'piece',
        'note',
        'urgent',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'ordered_at',
        'received_at',
    ];

    protected $casts = [
        'urgent' => 'boolean',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'ordered_at' => 'datetime',
        'received_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($record) {
            $nextId = (self::max('id') ?? 0) + 1;
            $record->code = 'DA' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
            $record->user_id = auth()->id();
        });
    }

    // creator user
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // related service
    public function service()
    {
        return $this->belongsTo(Service::class);
    }

  

    // purchase lines
    public function lines()
    {
        return $this->hasMany(PurchaseLine::class);
    }

    // history tracking
    public function histories()
    {
        return $this->hasMany(PurchaseDocumentHistory::class);
    }
}
