<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNcfStep1Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fournisseur'           => 'required|string|max:255',
            'reference_commande'    => 'nullable|string|max:255',
            'bon_livraison'         => 'nullable|string|max:255',
            'date_reception'        => 'nullable|date',
            'code_article'          => 'nullable|string|max:255',
            'produit_concerne'      => 'required|string|max:255',
            'quantite_receptionnee' => 'required|numeric|min:0',
            'quantite_non_conforme' => 'required|numeric|min:0',
            'detectee_par'          => 'required|in:approvisionnement,controle_qualite,production,autre',
            'detectee_par_autre'    => 'nullable|required_if:detectee_par,autre|string|max:255',
            'date_detection'        => 'required|date',
        ];
    }
}
