<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\PurchaseLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PurchaseLineController extends Controller
{

    public function index()
    {
        $lines = PurchaseLine::with('document')->latest()->get();
        return response()->json($lines);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'purchase_document_id' => 'required|exists:purchase_documents,id',
            'code' => 'nullable|string',
            'description' => 'required|string',
            'quantity' => 'required|numeric|min:1',
            'unit' => 'nullable|string',
            'estimated_price' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
                'message' => $validator->errors()->first()
            ], 422);
        }

        $validated = $validator->validated();

        $line = PurchaseLine::create($validated);

        return response()->json($line, 201);
    }

    // 🔍 Afficher une ligne d'achat spécifique
    public function show(PurchaseLine $purchaseLine)
    {
        $purchaseLine->load('files');
        return response()->json($purchaseLine);
    }

    // ✏️ Modifier une ligne d'achat
    public function update(Request $request, PurchaseLine $purchaseLine)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'nullable|string',
            'description' => 'nullable|string',
            'quantity' => 'nullable|numeric|min:1',
            'unit' => 'nullable|string',
            'estimated_price' => 'nullable|numeric',
            'total' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
                'message' => $validator->errors()->first()
            ], 422);
        }

        $validated = $validator->validated();

        $purchaseLine->update($validated);

        return response()->json($purchaseLine);
    }

    // ❌ Supprimer une ligne d'achat
    public function destroy(PurchaseLine $purchaseLine)
    {
        $purchaseLine->delete();
        return response()->json(['message' => 'Ligne supprimée avec succès.']);
    }
}
