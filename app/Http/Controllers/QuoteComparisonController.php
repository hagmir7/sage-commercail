<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuoteComparisonRequest;
use App\Models\QuoteComparison;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\LaravelPdf\Facades\Pdf;

class QuoteComparisonController extends Controller
{

    use AuthorizesRequests;

    public function index(): JsonResponse
    {
        return response()->json(
            QuoteComparison::latest()->paginate(15)
        );
    }

    public function store(StoreQuoteComparisonRequest $request): JsonResponse
    {
        $comparison = QuoteComparison::create($request->validated());

        return response()->json([
            'message' => 'Comparatif créé avec succès.',
            'data'    => $comparison,
        ], 201);
    }


    public function show(QuoteComparison $quoteComparison): JsonResponse
    {
        return response()->json(
            $quoteComparison->load(['offers', 'evaluations'])
        );
    }

    public function update(Request $request, QuoteComparison $quoteComparison): JsonResponse
    {
        $quoteComparison->update($request->validate([
            'reference'                => 'sometimes|string|unique:quote_comparisons,reference,' . $quoteComparison->id,
            'comparison_date'          => 'sometimes|date',
            'department'               => 'string',
            'purchase_object'          => 'sometimes|string',
            'selected_provider'            => 'nullable|string',
            'selection_justification'  => 'nullable|string',
            'purchasing_manager'       => 'nullable|string',
            'purchasing_manager_date'  => 'nullable|date',
            'general_director'         => 'nullable|string',
            'general_director_date'    => 'nullable|date',
            'status'                   => 'sometimes|in:brouillon,soumis,valide,rejete',
        ]));

        return response()->json([
            'message' => 'Comparatif mis à jour avec succès.',
            'data'    => $quoteComparison->fresh(),
        ]);
    }

    public function destroy(QuoteComparison $quoteComparison): JsonResponse
    {
        $quoteComparison->delete();
        return response()->json(['message' => 'Comparatif supprimé avec succès.']);
    }


    public function download(QuoteComparison $quoteComparison)
    {
        $quoteComparison->load(['offers', 'evaluations']);

        return Pdf::view('pdfs.comparison', ['comparison' => $quoteComparison])
            ->format('a4')
            ->margins(10, 10, 10, 10)
            ->footerHtml('
            <div style="
                font-size: 15px;
                text-align: center;
                width: 100%;
                color: #555;
            ">
                © Ce document ne doit être ni reproduit ni communiqué sans l\'autorisation d\'INTERCOCINA
            </div>
        ')
            ->name("comparatif_{$quoteComparison->reference}.pdf")
            ->download();
    }
}
