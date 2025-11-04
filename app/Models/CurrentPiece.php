<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrentPiece extends Model
{
    protected $table = 'F_DOCCURRENTPIECE';
    protected $primaryKey = 'cbMarq';
    public $timestamps = false;

    protected $fillable = [
        'DC_Domaine',
        'DC_IdCol',
        'DC_Souche',
        'DC_Piece',
        'cbProt',
        'cbMarq',
        'cbCreateur',
        'cbModification',
        'cbReplication',
        'cbFlag',
        'cbCreation',
        'cbCreationUser',
    ];
}
