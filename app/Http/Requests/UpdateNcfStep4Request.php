<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNcfStep4Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'causes_probables'              => 'required|string|max:5000',
            'cause_principale'              => 'required|string|max:5000',
            'action_corrective'             => 'required|string|max:5000',
            'responsable_action_corrective' => 'required|string|max:255',
            'date_previsionnelle'           => 'required|date|after_or_equal:today',
        ];
    }
}
