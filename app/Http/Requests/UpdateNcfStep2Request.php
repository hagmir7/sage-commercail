<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNcfStep2Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nature_nc'             => 'required|in:quantitative,qualitative,documentaire,autre',
            'types_ecart'           => 'required|array|min:1',
            'types_ecart.*'         => 'string|in:erreur_quantite,defaut_emballage,defaut_visuel,dimensions_non_conformes,produit_endommage,etiquetage_manquant,autre',
            'type_ecart_autre'      => 'nullable|required_if:types_ecart.*,autre|string|max:255',
            'description_detaillee' => 'required|string|max:5000',
            'preuves_jointes'       => 'required|boolean',
            'reference_lot'         => 'nullable|string|max:255',
            'gravite'               => 'required|in:mineure,majeure,critique',
        ];
    }
}
