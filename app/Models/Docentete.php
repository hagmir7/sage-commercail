<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Docentete extends Model
{
    protected $table = 'F_DOCENTETE';
    protected $primaryKey = 'DO_Piece';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $dateFormat = 'Y-d-m H:i:s.v';

    // const CREATED_AT = 'cbCreation';
    // const UPDATED_AT = 'cbModification';
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'DO_DateLivr' => 'datetime',
            'DO_DateLivrRealisee' => 'datetime',
            'DO_DateExpedition' => 'datetime',
        ];
    }

    // 🔹 Relation with Compte (client/fournisseur)
    public function compt(): BelongsTo
    {
        return $this->belongsTo(Compte::class, 'DO_Tiers', 'CT_Num');
    }

    // 🔹 Relation with document lines
    public function doclignes(): HasMany
    {
        return $this->hasMany(Docligne::class, 'DO_Piece', 'DO_Piece');
    }

    // 🔹 Relation with document lines from INTER connection
    public function doclignes_inter(): HasMany
    {
        return $this->setConnection('sqlsrv_inter')
            ->hasMany(Docligne::class, 'DO_Piece', 'DO_Piece');
    }

    // 🔹 Relation with document (custom table)
    public function document(): HasOne
    {
        return $this->hasOne(Document::class, 'docentete_id', 'cbMarq');
    }
}
