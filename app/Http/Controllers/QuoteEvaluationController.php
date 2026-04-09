<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\QuoteComparison;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuoteEvaluationController extends Controller
{
    public function storeOrUpdate(Request $request, QuoteComparison $quoteComparison): JsonResponse
    {
        $data = $request->validate([
            'provider_name'     => 'required|string|max:255',
            'price_score'       => 'required|integer|between:1,10',
            'delivery_score'    => 'required|integer|between:1,10',
            'technical_score'   => 'required|integer|between:1,10',
            'reliability_score' => 'required|integer|between:1,10',
            'payment_score'     => 'required|integer|between:1,10',
        ], [
            '*.between'              => 'Chaque note doit être entre 1 et 10.',
            'provider_name.required' => 'Le nom du prestataire est obligatoire.',
        ]);

        $eval = $quoteComparison->evaluations()->updateOrCreate(
            ['provider_name' => $data['provider_name']],
            $data
        );

        return response()->json([
            'message' => 'Évaluation enregistrée avec succès.',
            'data'    => $eval,
        ]);
    }
}