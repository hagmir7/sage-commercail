<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\QuoteComparison;
use App\Models\QuoteOffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuoteOfferController extends Controller
{
    public function store(Request $request, QuoteComparison $quoteComparison): JsonResponse
    {
        $data = $request->validate([
            'provider_name'        => 'required|string|max:255',
            'quote_reference'      => 'nullable|string',
            'quote_date'           => 'nullable|date',
            'validity_period'      => 'nullable|string',
            'product_designation'  => 'nullable|string',
            'quantity'             => 'required|numeric|min:0',
            'unit_price'           => 'required|numeric|min:0',
            'payment_conditions'   => 'nullable|string',
            'delivery_delay'       => 'nullable|string',
            'warranty'             => 'nullable|string',
            'technical_compliance' => 'nullable|string',
            'observations'         => 'nullable|string',
        ], [
            'provider_name.required' => 'Le nom du prestataire est obligatoire.',
            'quantity.required'      => 'La quantité est obligatoire.',
            'unit_price.required'    => 'Le prix unitaire est obligatoire.',
        ]);

        $data['total_price'] = $data['quantity'] * $data['unit_price'];

        return response()->json([
            'message' => 'Offre ajoutée avec succès.',
            'data'    => $quoteComparison->offers()->create($data),
        ], 201);
    }

    public function update(Request $request, QuoteComparison $quoteComparison, QuoteOffer $offer): JsonResponse
    {
        $data = $request->validate([
            'provider_name'        => 'sometimes|string|max:255',
            'quote_reference'      => 'nullable|string',
            'quote_date'           => 'nullable|date',
            'validity_period'      => 'nullable|string',
            'product_designation'  => 'nullable|string',
            'quantity'             => 'sometimes|numeric|min:0',
            'unit_price'           => 'sometimes|numeric|min:0',
            'payment_conditions'   => 'nullable|string',
            'delivery_delay'       => 'nullable|string',
            'warranty'             => 'nullable|string',
            'technical_compliance' => 'nullable|string',
            'observations'         => 'nullable|string',
        ]);

        if (isset($data['quantity']) || isset($data['unit_price'])) {
            $data['total_price'] = ($data['quantity'] ?? $offer->quantity) * ($data['unit_price'] ?? $offer->unit_price);
        }

        $offer->update($data);

        return response()->json([
            'message' => 'Offre mise à jour avec succès.',
            'data'    => $offer->fresh(),
        ]);
    }

    public function destroy(QuoteComparison $quoteComparison, QuoteOffer $offer): JsonResponse
    {
        $offer->delete();
        return response()->json(['message' => 'Offre supprimée avec succès.']);
    }
}