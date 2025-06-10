<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{

    protected $fillable = [
        'docentete_id',
        'piece',
        'type',
        'ref',
        'expedition',
        'transfer_by',
        'controlled_by',
        'validated_by',
        'client_id',
        'status_id',
        'piece_bl',
        'piece_fa',
        'user_id',
    ];

    // protected $dateFormat = 'Y-d-m H:i:s.v';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';


    // Relations
    public function docentete()
    {
        return $this->belongsTo(Docentete::class, 'docentete_id', 'cbMarq');
    }

    public function lines()
    {
        return $this->hasMany(Line::class, 'document_id', 'id');
    }


    public function transferBy()
    {
        return $this->belongsToMany(User::class, 'transfer_by');
    }

    public function palettes()
    {
        return $this->hasMany(Palette::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class, 'status_id', 'id');
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'document_companies')->withPivot(['status_id']);
    }


    public function validation(): bool
    {
        $this->load('lines.palettes');

        foreach ($this->lines as $line) {
            $totalPrepared = $line->palettes->sum(function ($palette) {
                return $palette->pivot->quantity ?? 0;
            });

            if ($totalPrepared < $line->quantity) {
                return false;
            }
        }
        return true;
    }


    public function validationCompany($companyId): bool
    {
        $this->load('lines.palettes');

        foreach ($this->lines->where('company_id', $companyId) as $line) {
            $totalPrepared = $line->palettes->where('company_id', $companyId)->sum(function ($palette) {
                return $palette->pivot->quantity ?? 0;
            });

            if ($totalPrepared < $line->quantity) {
                return false;
            }
        }

        return true;
    }
}
