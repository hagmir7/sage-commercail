<?php

namespace App\Http\Controllers;

use App\Exports\StockMovementsExport;
use App\Models\ArticleStock;
use App\Models\Company;
use App\Models\CompanyStock;
use App\Models\Emplacement;
use App\Models\Palette;
use App\Models\StockMovement;
use App\Services\StockMovementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;


class StockMovementController extends Controller
{

    protected StockMovementService $stockService;

    public function __construct(StockMovementService $stockService)
    {
        $this->stockService = $stockService;
    }



    public function list(Request $request, Company $company)
    {
        $request->validate([
            'per_page' => 'nullable|integer|min:1',
            'dates' => 'nullable|string',
            'types' => 'nullable|string',
        ]);

        return;

        $types = $request->filled('types') ? explode(',', $request->types) : null;

        if ($types && array_diff($types, ['IN', 'OUT', 'TRANSFER', 'RETURN'])) {
            return response()->json(['error' => 'Invalid types provided'], 422);
        }

        $movements = $company->movements()
            ->with(['movedBy:id,full_name', 'emplacement:id,code', 'to_emplacement'])
            ->when($types, fn($q) => $q->whereIn('movement_type', $types));

        $rolesToCheck = ['admin', 'production', 'super_admin'];

        if (!auth()->user()->hasAnyRole($rolesToCheck)) {
            $movements->where('moved_by', auth()->id());
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

        if ($types && array_diff($types, ['IN', 'OUT', 'TRANSFER', 'RETURN'])) {
            return response()->json(['error' => 'Invalid types provided'], 422);
        }

        $movements = StockMovement::with(['movedBy:id,full_name', 'emplacement:id,code', 'to_emplacement:id,code', 'to_company'])
            ->when($types, fn($q) => $q->whereIn('movement_type', $types));

        $rolesToCheck = ['admin', 'production', 'super_admin'];

        if (!auth()->user()->hasAnyRole($rolesToCheck)) {
            $movements->where('moved_by', auth()->id());
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

                $this->stockService->stockInsert(
                    $emplacement,
                    $article,
                    $request->quantity,
                    $conditionMultiplier,
                    $request->type_colis,
                    intval($request->quantity),
                );
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

            $conditionMultiplier = $request->condition ? (float) $request->condition : 1.0;

            DB::transaction(function () use ($article, $request, $conditionMultiplier, $emplacement, $companyId) {
                // ✅ Calculate QTE to remove
                $qte_value = ($request->type_colis === "Palette" || $request->type_colis === "Carton")
                    ? $request->palettes * $conditionMultiplier
                    : $request->quantity;



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
                $this->stockOut($emplacement, $article, $qte_value);
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


    public function transfer(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'emplacement_code' => 'required|string|max:255|min:3|exists:emplacements,code',
                'second_emplacement_code' => 'nullable|string|max:255|min:3|exists:emplacements,code',
                'code_article'     => 'required|string',
                'quantity'         => 'required|numeric|min:1',
                'condition'        => 'nullable|numeric|min:1',
                'type_colis'       => 'nullable|in:Piece,Palette,Carton',
                'palettes'         => 'required_if:type_colis,Palette,Carton|numeric|min:1',
                'company'          => 'nullable|numeric'
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

            $conditionMultiplier = $request->condition ? (float) $request->condition : 1.0;


            DB::transaction(function () use ($article, $request, $conditionMultiplier, $emplacement, $companyId) {
                $qte_value = ($request->type_colis === "Palette" || $request->type_colis === "Carton")
                    ? $request->palettes * $conditionMultiplier
                    : $request->quantity;


                $second_emplacement = Emplacement::where('code', $request->second_emplacement_code)->first();



                StockMovement::create([
                    'code_article'     => $request->code_article,
                    'designation'      => $article->description,
                    'emplacement_id'   => $emplacement->id,
                    'movement_type'    => "TRANSFER",
                    'article_stock_id' => $article->id,
                    'quantity'         => $qte_value,
                    'moved_by'         => auth()->id(),
                    'company_id'       => auth()?->user()?->compnay_id || 1,
                    'movement_date'    => now(),
                    'to_company_id'    => $companyId,
                    'to_emplacement_id' => !empty($request->second_emplacement_code) ? $second_emplacement->id : null
                ]);

                $this->stockOut($emplacement, $article, $qte_value);


                if (!empty($request->second_emplacement_code)) {
                    $second_emplacement = Emplacement::where('code', $request->second_emplacement_code)->first();
                    $this->stockInsert($second_emplacement, $article, $qte_value, $conditionMultiplier, $request->type_colise, $request->quantity);
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


    public function stockInsert($emplacement, $article, $qte_value, $conditionMultiplier=null, $type_colis=null, $quantity=null)
    {
        DB::transaction(function () use ($emplacement, $article, $qte_value, $conditionMultiplier, $type_colis, $quantity) {

            //  If type is "Palette" — create one per quantity
            if ($type_colis === "Palette") {
                for ($i = 1; $i <= intval($quantity); $i++) {
                    $palette = Palette::create([
                        "code"           => $this->generatePaletteCode(),
                        "emplacement_id" => $emplacement->id,
                        "company_id"     => $emplacement->depot->company_id ?? null,
                        "user_id"        => auth()->id(),
                        "type"           => "Stock",
                    ]);

                    $article->palettes()->attach($palette->id, [
                        'quantity' => floatval($conditionMultiplier),
                    ]);
                }

                return;
            }

            // For non-palette (grouped stock)
            // Find or create a palette for this emplacement
            $palette = Palette::firstOrCreate(
                ['emplacement_id' => $emplacement->id, 'type' => 'Stock'],
                [
                    "code"       => $this->generatePaletteCode(),
                    "company_id" => $emplacement->depot->company_id ?? null,
                    "user_id"    => auth()->id(),
                    "type"       => "Stock"
                ]
            );

            // Check if article already exists in that palette
            $existingPivot = $article->palettes()
                ->where('palette_id', $palette->id)
                ->first();

            if ($existingPivot) {
                // Update quantity
                $article->palettes()->updateExistingPivot(
                    $palette->id,
                    ['quantity' => DB::raw('quantity + ' . (float) $qte_value)]
                );
            } else {
                // Attach new article
                $article->palettes()->attach($palette->id, ['quantity' => $qte_value]);
            }
        });
    }


    public function stockOut($emplacement, $article, $qte_value, $type=null)
    {
        // 1️⃣ Get all palettes in this emplacement that have this article
        $palettes = Palette::where('emplacement_id', $emplacement->id)
            ->where('type', $type ? $type : 'Stock')
            ->whereHas('articles', function ($q) use ($article) {
                $q->where('article_stocks.id', $article->id);
            })
            ->with(['articles' => function ($q) use ($article) {
                $q->where('article_stocks.id', $article->id);
            }])
            ->lockForUpdate()
            ->get();

        if ($palettes->isEmpty() && $type == 'Stock') {
            throw new \Exception("Article non trouvé dans cet emplacement.");
        }

        // 2️⃣ Calculate total available quantity
        $totalQty = $palettes->sum(fn($p) => $p->articles->first()?->pivot->quantity ?? 0);

        if ($totalQty < $qte_value) {
            throw new \Exception("Stock insuffisant dans l’emplacement.");
        }

        // 3️⃣ Deduct from palettes one by one
        $remaining = $qte_value;

        foreach ($palettes as $palette) {
            $pivot = $palette->articles->first()->pivot;
            $available = $pivot->quantity;

            if ($available >= $remaining) {
                $palette->articles()->updateExistingPivot($article->id, [
                    'quantity' => DB::raw('quantity - ' . $remaining)
                ]);
                break;
            } else {
                // empty this palette and move to next
                $palette->articles()->updateExistingPivot($article->id, [
                    'quantity' => 0
                ]);
                $remaining -= $available;
            }
        }
    }


    public function stockTransfer($sourceEmplacement, $destinationEmplacement, $article, float $quantity)
    {
        DB::transaction(function () use (
            $sourceEmplacement,
            $destinationEmplacement,
            $article,
            $quantity
        ) {
            $this->stockOut($sourceEmplacement, $article, $quantity);

            $this->stockInsert(
                $destinationEmplacement,
                $article,
                $quantity,
                'Piece',
                null,
                null
            );
        });
    }

    public function deleteMovement(StockMovement $stock_movement)
    {
        try {
            DB::transaction(function () use ($stock_movement) {
                // Validate emplacement exists
                $emplacement = Emplacement::find($stock_movement->emplacement_id);
                if (!$emplacement) {
                    throw new RuntimeException(
                        "Emplacement introuvable avec l'ID : {$stock_movement->emplacement_id}"
                    );
                }

                // Validate article exists
                $article = ArticleStock::find($stock_movement->article_stock_id);
                if (!$article) {
                    throw new RuntimeException(
                        "Article introuvable avec l'ID : {$stock_movement->article_stock_id}"
                    );
                }

                // Validate quantity
                if ($stock_movement->quantity <= 0) {
                    throw new RuntimeException(
                        "Quantité invalide : {$stock_movement->quantity}"
                    );
                }

                // Rollback the movement based on type
                switch ($stock_movement->movement_type) {
                    case 'IN':
                        $this->stockService->rollbackStockInsert(
                            $emplacement,
                            $article,
                            $stock_movement->quantity
                        );
                        break;

                    case 'OUT':
                        $this->stockService->rollbackStockOut(
                            $emplacement,
                            $article,
                            $stock_movement->quantity
                        );
                        break;

                    case 'RETURN':
                        $this->stockService->rollbackStockInsert(
                            $emplacement,
                            $article,
                            $stock_movement->quantity
                        );
                        break;

                    case 'TRANSFER':
                        if ($stock_movement->to_emplacement_id) {
                            $destinationEmplacement = Emplacement::find(
                                $stock_movement->to_emplacement_id
                            );
                            if (!$destinationEmplacement) {
                                throw new RuntimeException(
                                    "Emplacement de destination introuvable"
                                );
                            }
                            $this->stockService->rollbackTransfer(
                                $emplacement,
                                $destinationEmplacement,
                                $article,
                                $stock_movement->quantity
                            );
                        } else {
                            throw new RuntimeException(
                                "Impossible d'annuler le transfert : destination manquante"
                            );
                        }
                        break;
                    default:
                        throw new RuntimeException(
                            "Type de mouvement inconnu : {$stock_movement->movement_type}"
                        );
                }
                $stock_movement->delete();
            });

            return response()->json([
                'success' => true,
                'message' => 'Mouvement supprimé avec succès',
                'data' => [
                    'movement_id' => $stock_movement->id,
                    'movement_type' => $stock_movement->movement_type,
                    'code_article' => $stock_movement->code_article ?? $stock_movement->article_stock_id,
                    'quantity' => $stock_movement->quantity,
                    'emplacement' => $stock_movement->emplacement->code ?? null
                ]
            ], 200);
        } catch (RuntimeException $e) {
            \Log::alert($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Error deleting stock movement', [
                'movement_id' => $stock_movement->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur inattendue s\'est produite',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function exportMovements(Request $request)
    {
        // $filters = $request->all();
        $filters = [
            'dateRange' => $request->input('dateRange'),
            'users' => $request->input('users', []),
            'category' => $request->input('category'),
            'depots' => $request->input('depots', []),
            'search' => $request->input('search'),
            'types' => $request->input('types', []),
            'dates' => $request->input('dates', []),
        ];
        $fileName = 'stock_movements_' . now()->format('Y_m_d_His') . '.xlsx';
        return Excel::download(new StockMovementsExport($filters), $fileName);
    }
}
