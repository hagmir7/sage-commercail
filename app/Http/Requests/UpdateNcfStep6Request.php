<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNcfStep6Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision_finale' => 'required|in:accepte_apres_correction,refuse_definitivement,accepte_avec_derogation',
            'signatures'      => 'required|array|min:1',
            'signatures.*.entite'     => 'required|in:achats,direction',
            'signatures.*.nom_prenom' => 'required|string|max:255',
            'signatures.*.date'       => 'required|date',
            'signatures.*.visa'       => 'nullable|string|max:255',
        ];
    }
}
