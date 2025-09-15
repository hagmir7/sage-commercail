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
        return $this->hasMany(Line::class); // Adjust the namespace if needed
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



    public function validation(): bool
    {
        $this->load('lines.palettes');
            $lines = $this->lines
            ->where('ref', '!=', 'SP000001')
            ->whereNotIn('design', ['Special', '', 'special']);

        foreach ($lines as $line) {
            $totalToPrepare = floatval($line->docligne->DL_Qte);
            $totalPrepared = floatval($line->docligne->DL_QteBL);

            if ($totalToPrepare != $totalPrepared) {
                return false;
            }
        }

        $lines = $this->lines
            ->where('ref', 'SP000001')
            ->whereIn('design', ['', 'Special', 'special']);

        foreach($lines as $line){
            $line->delete();
        }
        return true;
    }


    public function validationCompany($companyId): bool
    {
        $this->load('lines.palettes');
        $lines = $this->lines()
            ->where('company_id', $companyId)
            ->where('ref', '!=', 'SP000001')
            ->whereNotIn('design', ['Special', '', 'special'])
            ->get();

        foreach ($lines as $line) {
            $totalPrepared = floatval($line->docligne->DL_QteBL);

            if ($totalPrepared < $line->docligne->DL_Qte) {
                return false;
            }
        }

        // cleanup
        $specials = $this->lines
            ->where('ref', 'SP000001')
            ->whereIn('design', ['', 'Special', 'special']);

        foreach ($specials as $line) {
            $line->delete();
        }

        return true;
    }

}
