<?php

namespace App\Http\Controllers;

use App\Models\ArticleStock;
use App\Models\Company;
use App\Models\CompanyStock;
use App\Models\Emplacement;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

// use App\Models\Palette;


class StockMovementController extends Controller
{




    public function list(Request $request, Company $company)
    {
        $request->validate([
            'per_page' => 'nullable|integer|min:1',
            'dates' => 'nullable|string',
            'types' => 'nullable|string',
        ]);

        $types = $request->filled('types') ? explode(',', $request->types) : null;

        if ($types && array_diff($types, ['IN', 'OUT', 'TRANSFER'])) {
            return response()->json(['error' => 'Invalid types provided'], 422);
        }

        $movements = $company->movements()
            ->with(['movedBy:id,full_name'])
            ->when($types, fn($q) => $q->whereIn('movement_type', $types));

        if (!auth()->user()->hasRole('admin') && !auth()->user()->hasRole('supper_admin')) {
            $movements->where("moved_by", auth()->id());
        }

        $depots = $request->filled('depots') ? explode(',', $request->depots) : null;
        $users = $request->filled('users') ? explode(',', $request->users) : null;

        if (!empty($depots)) {
            $movements->filterByDepots($depots);
        }

        if ($request->filled('emplacement') && $request->emplacement !== '') {
            $movements->filterByEmplacement($request->emplacement);
        }

        if (!empty($users)) {
            $movements->filterByUsers($users);
        }

        if ($request->filled('search') && $request->search !== '') {
            $movements->search($request->search);
        }

        if ($request->filled('category') && $request->category !== '') {
            $movements->filterByCategory($request->category);
        }

        if ($request->filled('dates') && $request->dates !== ',') {
            $movements->filterByDates($request->dates);
        }

        return response()->json([
            'company' => $company,
            'movements' => $movements->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 30)),
        ]);
    }

    public function generatePaletteCode()
    {
        $lastCode = DB::table('palettes')
            ->where('code', 'like', 'PALS%')
            ->orderBy('id', 'desc')
            ->value('code');

        if (!$lastCode) {
            $nextNumber = 1;
        } else {
            $number = (int) substr($lastCode, 4);
            $nextNumber = $number + 1;
        }
        return 'PALS' . str_pad($nextNumber, 8, '0', STR_PAD_LEFT);
    }


    public function in(Request $request)
    {

        try {
            $validator = Validator::make($request->all(), [
                'emplacement_code' => 'required|string|max:255|min:3|exists:emplacements,code',
                'code_article' => 'string|required',
                'quantity' => 'numeric|required|min:0',
                'condition' => 'nullable',
                'type_colis' => 'nullable|in:Piece,Palette,Carton',
                'palettes' => 'numeric',
                'company' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors()
                ], 422);
            }

            $article = ArticleStock::where('code', $request->code_article)->first();
            $emplacement = Emplacement::where("code", $request->emplacement_code)->first();

            if (!$article) {
                return response()->json([
                    'errors' => ['article' => 'Article non trouvé']
                ], 404);
            }

            if (!$emplacement) {
                return response()->json([
                    'errors' => ['article' => 'Emplacement non trouvé']
                ], 404);
            }



            $conditionMultiplier = $request->condition ? (float) $request->condition : 1.0;

            DB::transaction(function () use ($article, $request, $conditionMultiplier, $emplacement) {
                try {

                    StockMovement::create([
                        'code_article' => $request->code_article,
                        'designation' => $article->description,
                        'emplacement_id' => $emplacement->id,
                        'movement_type' => "IN",
                        'article_stock_id' => $article->id,
                        'quantity' => $request->quantity,
                        'moved_by' => auth()->id(),
                        'company_id' => intval($request?->company ?? 1),
                        'movement_date' => now(),
                    ]);

                    if ($request->type_colis == "Palette" || $request->type_colis == "Carton") {
                        $qte_value = $request->palettes * $conditionMultiplier;
                    } else {
                        $qte_value = $request->quantity;
                    }


                    $company_stock = CompanyStock::where('code_article', $request->code_article)
                        ->where('company_id', $request->company)
                        ->first();

                    if ($company_stock) {
                        // Update existing record
                        $company_stock->quantity = $company_stock->quantity + $qte_value;
                        $company_stock->save();
                    } else {
                        // Create new record
                        $company_stock = CompanyStock::create([
                            'code_article' => $request->code_article,
                            'designation' => $article->description,
                            'company_id' => $request->company,
                            'quantity' => $qte_value
                        ]);
                    }



                    // Create Palette
                    // if ($request->type_colis == "Palette") {
                    //     for ($i = 1; $i <= intval($request->palettes); $i++) {
                    //         $palette = Palette::create([
                    //             "code" => $this->generatePaletteCode(),
                    //             "emplacement_id" => $emplacement->id,
                    //             "company_id" => intval($request?->company ?? 1),
                    //             "user_id" => auth()->id(),
                    //             "type" => "Inventaire",
                    //         ]);
                    //     }
                    // } else {
                    //     $palette = Palette::firstOrCreate(
                    //         [
                    //             "emplacement_id" => $emplacement->id,
                    //         ],
                    //         [
                    //             "code" => $this->generatePaletteCode(),
                    //             "company_id" => intval($request?->company ?? 1),
                    //             "user_id" => auth()->id(),
                    //             "type" => "Inventaire"
                    //         ]
                    //     );
                    // }
                } catch (\Exception $transactionException) {
                    throw $transactionException;
                }
            });

            return response()->json(['message' => 'Stock successfully inserted or updated.']);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Database error in insert function', [
                'error' => $e->getMessage(),
                'sql' => $e->getSql() ?? 'N/A',
                'bindings' => $e->getBindings() ?? [],
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Une erreur de base de données s\'est produite.',
                'error' => 'Database error'
            ], 500);
        } catch (\Exception $e) {
            Log::error('Unexpected error in insert function', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return response()->json([
                'message' => 'Une erreur inattendue s\'est produite.',
                'error' => 'Internal server error'
            ], 500);
        }
    }


    public function out(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'emplacement_code' => 'required|string|max:255|min:3|exists:emplacements,code',
                'code_article' => 'string|required',
                'quantity' => 'numeric|required|min:0',
                'condition' => 'nullable',
                'type_colis' => 'nullable|in:Piece,Palette,Carton',
                'palettes' => 'numeric',
                'company' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors()->first(),
                    'errors' => $validator->errors()
                ], 422);
            }

            $article = ArticleStock::where('code', $request->code_article)->first();
            $emplacement = Emplacement::where("code", $request->emplacement_code)->first();

            if (!$article) {
                return response()->json([
                    'errors' => ['article' => 'Article non trouvé']
                ], 404);
            }

            if (!$emplacement) {
                return response()->json([
                    'errors' => ['article' => 'Emplacement non trouvé']
                ], 404);
            }

            $conditionMultiplier = $request->condition ? (float) $request->condition : 1.0;

            // Calculate quantity to be removed
            if ($request->type_colis == "Palette" || $request->type_colis == "Carton") {
                $qte_value = $request->palettes * $conditionMultiplier;
            } else {
                $qte_value = $request->quantity;
            }

            // Check if sufficient stock exists
            $company_stock = CompanyStock::where('code_article', $request->code_article)
                ->where('company_id', $request->company)
                ->first();

            if (!$company_stock || $company_stock->quantity < $qte_value) {
                return response()->json([
                    'errors' => ['stock' => 'Stock insuffisant pour cette opération'],
                    'available_quantity' => $company_stock ? $company_stock->quantity : 0,
                    'requested_quantity' => $qte_value
                ], 400);
            }

            DB::transaction(function () use ($article, $request, $conditionMultiplier, $emplacement, $qte_value, $company_stock) {
                try {
                    StockMovement::create([
                        'code_article' => $request->code_article,
                        'designation' => $article->description,
                        'emplacement_id' => $emplacement->id,
                        'movement_type' => "OUT",
                        'article_stock_id' => $article->id,
                        'quantity' => $request->quantity,
                        'moved_by' => auth()->id(),
                        'company_id' => intval($request?->company ?? 1),
                        'movement_date' => now(),
                    ]);


                    $company_stock->quantity = $company_stock->quantity - $qte_value;
                    $company_stock->save();
                } catch (\Exception $transactionException) {
                    throw $transactionException;
                }
            });

            return response()->json([
                'message' => 'Stock successfully removed.',
                'remaining_quantity' => $company_stock->quantity
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Database error in out function', [
                'error' => $e->getMessage(),
                'sql' => $e->getSql() ?? 'N/A',
                'bindings' => $e->getBindings() ?? [],
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Une erreur de base de données s\'est produite.',
                'error' => 'Database error'
            ], 500);
        } catch (\Exception $e) {
            Log::error('Unexpected error in out function', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return response()->json([
                'message' => 'Une erreur inattendue s\'est produite.',
                'error' => 'Internal server error'
            ], 500);
        }
    }
}
