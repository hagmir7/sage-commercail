<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNcfStep3Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision_provisoire' => 'required|in:accepte,accepte_sous_reserve,refuse_retour',
            'mesures_immediates'  => 'required|string|max:5000',
            'responsable_action'  => 'required|string|max:255',
            'date_execution'      => 'required|date',
        ];
    }
}
