<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNcfStep5Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'responsable_suivi'  => 'required|string|max:255',
            'date_verification'  => 'required|date',
            'action_realisee'    => 'required|boolean',
            'action_efficace'    => 'required|boolean',
            'fnc_reference'      => 'nullable|string|max:255',
            'date_cloture'       => 'nullable|date',
        ];
    }
}
