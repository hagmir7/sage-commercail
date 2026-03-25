<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Machine extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'machines';

    protected $fillable = [
        'family',
        'ref_machine',
        'group_code',
        'machine_id',
        'machine_name',
        'serial_number',
        'alias',
        'manufacturer',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeByFamily($query, string $family)
    {
        return $query->where('family', strtoupper($family));
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('machine_id',   'like', "%{$term}%")
              ->orWhere('alias',       'like', "%{$term}%")
              ->orWhere('machine_name','like', "%{$term}%")
              ->orWhere('ref_machine', 'like', "%{$term}%")
              ->orWhere('manufacturer','like', "%{$term}%")
              ->orWhere('serial_number','like',"%{$term}%");
        });
    }
}