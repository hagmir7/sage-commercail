<?php

namespace App\Http\Controllers;

use App\Models\ArticleStock;
use App\Models\Company;
use App\Models\CompanyStock;
use App\Models\Emplacement;
use App\Models\Palette;
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
            ->with(['movedBy:id,full_name', 'emplacement:id,code'])
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


    public function listGeneral(Request $request, Company $company)
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

        $movements = StockMovement::with(['movedBy:id,full_name', 'emplacement:id,code'])
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



    public function update(StockMovement $stock_movement, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'emplacement_code' => 'required|string|max:255|min:3|exists:emplacements,code',
            'quantity' => 'numeric|required|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $emplacement = Emplacement::where('code', $request->emplacement_code)->first();

        $stock_movement->update([
            'emplacement_id' => $emplacement->id,
            'quantity' => $request->quantity,
        ]);

        return response()->json([
            'message' => 'Stock movement updated successfully.',
            'data' => $stock_movement
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
            // ✅ Validation
            $validator = Validator::make($request->all(), [
                'emplacement_code' => 'required|string|max:255|min:3|exists:emplacements,code',
                'code_article'     => 'required|string',
                'quantity'         => 'required|numeric|min:0',
                'condition'        => 'nullable|numeric|min:1',
                'type_colis'       => 'nullable|in:Piece,Palette,Carton',
                'palettes'         => 'required_if:type_colis,Palette,Carton|numeric|min:1',
                'company'          => 'required|numeric'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors()->first(),
                    'errors'  => $validator->errors()
                ], 422);
            }

            $companyId   = intval($request->company ?? 1);
            $article     = ArticleStock::where('code', $request->code_article)->first();
            $emplacement = Emplacement::where("code", $request->emplacement_code)->first();

            if (!$article) {
                return response()->json([
                    'errors' => ['article' => 'Article non trouvé']
                ], 404);
            }

            if (!$emplacement) {
                return response()->json([
                    'errors' => ['emplacement' => 'Emplacement non trouvé']
                ], 404);
            }

            $conditionMultiplier = $request->condition ? (float) $request->condition : 1.0;

            DB::transaction(function () use ($article, $request, $conditionMultiplier, $emplacement, $companyId) {
                // ✅ Log stock movement
                StockMovement::create([
                    'code_article'     => $request->code_article,
                    'designation'      => $article->description,
                    'emplacement_id'   => $emplacement->id,
                    'movement_type'    => request()->is('*return*') ? "RETURN" : "IN",
                    'article_stock_id' => $article->id,
                    'quantity'         => $request->quantity,
                    'moved_by'         => auth()->id(),
                    'company_id'       => $companyId,
                    'movement_date'    => now(),
                ]);



                // ✅ Calculate QTE
                if ($request->type_colis === "Palette" || $request->type_colis === "Carton") {
                    $qte_value = $request->palettes * $conditionMultiplier;
                } else {
                    $qte_value = $request->quantity;
                }

                // ✅ Update company stock
                $company_stock = CompanyStock::where('code_article', $request->code_article)
                    ->where('company_id', $companyId)
                    ->first();

                if ($company_stock) {
                    $company_stock->quantity += $qte_value;
                    $company_stock->save();
                } else {
                    CompanyStock::create([
                        'code_article' => $request->code_article,
                        'designation'  => $article->description,
                        'company_id'   => $companyId,
                        'quantity'     => $qte_value
                    ]);
                }

                // ✅ Update emplacement pivot safely
                $existing = $emplacement->articles()->find($article->id);

                if ($existing) {
                    $emplacement->articles()->updateExistingPivot($article->id, [
                        'quantity' => DB::raw('quantity + ' . $qte_value)
                    ]);
                } else {
                    $emplacement->articles()->attach($article->id, ['quantity' => $qte_value]);
                }

                // ✅ Handle palettes
                if ($request->type_colis === "Palette") {
                    for ($i = 1; $i <= intval($request->quantity); $i++) {
                        $palette = Palette::create([
                            "code"           => $this->generatePaletteCode(),
                            "emplacement_id" => $emplacement->id,
                            "company_id"     => $companyId,
                            "user_id"        => auth()->id(),
                            "type"           => "Stock",
                        ]);
                        $article->palettes()->attach($palette->id, ['quantity' => floatval($conditionMultiplier)]);
                    }
                } else {
                    // One palette per emplacement
                    $palette = Palette::firstOrCreate(
                        ["emplacement_id" => $emplacement->id],
                        [
                            "code"       => $this->generatePaletteCode(),
                            "company_id" => $companyId,
                            "user_id"    => auth()->id(),
                            "type"       => "Stock"
                        ]
                    );

                    if ($article->palettes()->where('palette_id', $palette->id)->exists()) {
                        $article->palettes()->updateExistingPivot(
                            $palette->id,
                            ['quantity' => DB::raw('quantity + ' . (int) $qte_value)]
                        );
                    } else {
                        $article->palettes()->attach($palette->id, ['quantity' => $qte_value]);
                    }
                }
            });

            return response()->json(['message' => 'Stock successfully inserted or updated.']);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Database error in insert function', [
                'error'        => $e->getMessage(),
                'sql'          => $e->getSql() ?? 'N/A',
                'bindings'     => $e->getBindings() ?? [],
                'trace'        => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Une erreur de base de données s\'est produite.',
                'error'   => 'Database error'
            ], 500);
        } catch (\Exception $e) {
            Log::error('Unexpected error in insert function', [
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'line'         => $e->getLine(),
                'file'         => $e->getFile()
            ]);

            return response()->json([
                'message' => 'Une erreur inattendue s\'est produite.',
                'error'   => 'Internal server error'
            ], 500);
        }
    }



    public function out(Request $request)
    {
        try {
            // ✅ Validation
            $validator = Validator::make($request->all(), [
                'emplacement_code' => 'required|string|max:255|min:3|exists:emplacements,code',
                'code_article'     => 'required|string',
                'quantity'         => 'required|numeric|min:1',
                'condition'        => 'nullable|numeric|min:1',
                'type_colis'       => 'nullable|in:Piece,Palette,Carton',
                'palettes'         => 'required_if:type_colis,Palette,Carton|numeric|min:1',
                'company'          => 'required|numeric'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors()->first(),
                    'errors'  => $validator->errors()
                ], 422);
            }

            $companyId   = intval($request->company ?? 1);
            $article     = ArticleStock::where('code', $request->code_article)->first();
            $emplacement = Emplacement::where("code", $request->emplacement_code)->first();

            if (!$article) {
                return response()->json(['errors' => ['article' => 'Article non trouvé']], 404);
            }

            if (!$emplacement) {
                return response()->json(['errors' => ['emplacement' => 'Emplacement non trouvé']], 404);
            }

            $conditionMultiplier = $request->condition ? (float) $request->condition : 1.0;

            DB::transaction(function () use ($article, $request, $conditionMultiplier, $emplacement, $companyId) {
                // ✅ Calculate QTE to remove
                $qte_value = ($request->type_colis === "Palette" || $request->type_colis === "Carton")
                    ? $request->palettes * $conditionMultiplier
                    : $request->quantity;

                // ✅ Check company stock availability
                $company_stock = CompanyStock::where('code_article', $request->code_article)
                    ->where('company_id', $companyId)
                    ->first();

                if (!$company_stock || $company_stock->quantity < $qte_value) {
                    throw new \Exception("Stock insuffisant pour cet article.");
                }

                // ✅ Log stock movement
                StockMovement::create([
                    'code_article'     => $request->code_article,
                    'designation'      => $article->description,
                    'emplacement_id'   => $emplacement->id,
                    'movement_type'    => "OUT",
                    'article_stock_id' => $article->id,
                    'quantity'         => $qte_value,
                    'moved_by'         => auth()->id(),
                    'company_id'       => $companyId,
                    'movement_date'    => now(),
                ]);

                // ✅ Update company stock
                $company_stock->quantity -= $qte_value;
                $company_stock->save();

                // ✅ Update emplacement pivot
                $existing = $emplacement->articles()->find($article->id);
                if ($existing) {
                    $currentQty = $existing->pivot->quantity;
                    if ($currentQty < $qte_value) {
                        throw new \Exception("Stock insuffisant dans l’emplacement.");
                    }
                    $emplacement->articles()->updateExistingPivot($article->id, [
                        'quantity' => DB::raw('quantity - ' . $qte_value)
                    ]);
                } else {
                    throw new \Exception("Article non trouvé dans cet emplacement.");
                }

                // ✅ Handle palettes (reduce instead of add)
                $palette = $article->palettes()
                    ->where('emplacement_id', $emplacement->id)
                    ->first();

                if ($palette) {
                    $currentPaletteQty = $palette->pivot->quantity;
                    if ($currentPaletteQty <= $qte_value) {
                        // Remove pivot if quantity exhausted
                        $article->palettes()->detach($palette->id);
                    } else {
                        $article->palettes()->updateExistingPivot(
                            $palette->id,
                            ['quantity' => DB::raw('quantity - ' . (int) $qte_value)]
                        );
                    }
                }
            });

            return response()->json(['message' => 'Stock successfully removed.']);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Database error in out function', [
                'error'        => $e->getMessage(),
                'sql'          => $e->getSql() ?? 'N/A',
                'bindings'     => $e->getBindings() ?? [],
                'trace'        => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json(['message' => 'Erreur base de données.', 'error' => 'Database error'], 500);
        } catch (\Exception $e) {
            Log::error('Unexpected error in out function', [
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'line'         => $e->getLine(),
                'file'         => $e->getFile()
            ]);

            return response()->json(['message' => $e->getMessage(), 'error' => 'Internal server error'], 500);
        }
    }
}
