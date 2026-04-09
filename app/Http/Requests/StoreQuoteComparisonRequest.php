<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuoteComparisonRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'reference'        => 'required|string|unique:quote_comparisons,reference',
            'comparison_date'  => 'required|date',
            'department'       => 'required',
            'purchase_object'  => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'reference.required'       => 'La référence est obligatoire.',
            'reference.unique'         => 'Cette référence existe déjà.',
            'comparison_date.required' => 'La date du comparatif est obligatoire.',
            'department.required'      => 'Le service demandeur est obligatoire.',
            'purchase_object.required' => "L'objet de l'achat est obligatoire.",
        ];
    }
}