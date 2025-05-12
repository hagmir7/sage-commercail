<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


class DocenteteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'DO_Piece' => $this->DO_Piece,
            'DO_Ref' => $this->DO_Ref,
            'DO_Tiers' => $this->DO_Tiers,
            'DO_Expedit' => $this->DO_Expedit,
            'cbMarq' => $this->cbMarq,
            'Type' => $this->Type,
            'DO_Reliquat' => $this->DO_Reliquat,
            'DO_Date' => $this->DO_Date,
            'DO_DateLivr' => $this->DO_DateLivr,
            'doclignes' => $this->doclignes->map(function ($line) {
                return [
                    'DO_Piece' => $line->DO_Piece,
                    'AR_Ref' => $line->AR_Ref,
                    'DO_Domaine' => $line->DO_Domaine,
                    'DL_Qte' => $line->DL_Qte,
                    'Nom' => $line->Nom,
                    'Hauteur' => $line->Hauteur,
                    'Largeur' => $line->Largeur,
                    'Profondeur' => $line->Profondeur,
                    'Langeur' => $line->Langeur,
                    'Couleur' => $line->Couleur,
                    'Chant' => $line->Chant,
                    'Episseur' => $line->Episseur,
                    'cbMarq' => $line->cbMarq,
                ];
            }),
        ];
    }
}
