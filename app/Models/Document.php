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
        'piece_bc',
        'user_id',
    ];

    // protected $dateFormat = 'Y-d-m H:i:s.v';

    // const CREATED_AT = 'created_at';
    // const UPDATED_AT = 'updated_at';
     public $timestamps = true;


    // Relations
    public function docentete()
    {
        return $this->belongsTo(Docentete::class, 'docentete_id', 'cbMarq');
    }

    public function lines()
    {
        return $this->hasMany(Line::class);
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
        return $this->belongsToMany(Company::class, 'document_companies')->withPivot(['status_id', 'printed', 'updated']);
    }


    // In Document.php
    public function printers()
    {
        return $this->belongsToMany(User::class, 'user_document_printer', 'document_id', 'user_id')
            ->withTimestamps();
    }




public function validation(): bool
{
    // Load related lines and palettes
    $this->load('lines.palettes');

    // Filter normal lines (excluding special references/designs)
    $lines = $this->lines
        ->where('ref', '!=', 'SP000001')
        ->whereNotIn('design', ['Special', '', 'special']);

    foreach ($lines as $line) {
        // Skip lines without a related docligne
        if (!$line->docligne) {
            continue;
        }

        $totalToPrepare = floatval($line->docligne->EU_Qte);
        $totalPrepared = floatval($line->docligne->DL_QteBL);

        // Check if all companies have status_id 8
        $allStatus = $this->companies()
            ->where('status_id', 8)
            ->count() === $this->companies()->count();

        if ($totalToPrepare != $totalPrepared) {
            return $allStatus ? true : false;
        }
    }

    // Remove special lines (SP000001)
    $specials = $this->lines
        ->where('ref', 'SP000001')
        ->whereIn('design', ['', 'Special', 'special']);

    foreach ($specials as $line) {
        $line->delete();
    }

    return true;
}

public function validationCompany($companyId): bool
{
    // Load lines and palettes
    $this->load('lines.palettes');

    // Filter lines for the given company (excluding special references/designs)
    $lines = $this->lines()
        ->where('company_id', $companyId)
        ->where('ref', '!=', 'SP000001')
        ->whereNotIn('design', ['Special', '', 'special'])
        ->get();

    foreach ($lines as $line) {
        // Skip lines without a related docligne
        if (!$line->docligne) {
            continue;
        }

        $totalPrepared = floatval($line->docligne->DL_QteBL);
        $totalToPrepare = floatval($line->docligne->EU_Qte);

        if ($totalPrepared < $totalToPrepare) {
            return false;
        }
    }

    // Cleanup special lines for this document
    $specials = $this->lines
        ->where('ref', 'SP000001')
        ->whereIn('design', ['', 'Special', 'special']);

    foreach ($specials as $line) {
        $line->delete();
    }

    return true;
}

}
