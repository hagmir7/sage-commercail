<?php

namespace App\Http\Controllers;

use App\Exports\StockMovementsExport;
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
use Maatwebsite\Excel\Facades\Excel;


class StockMovementController extends Controller
{

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

        if ($types && array_diff($types, ['IN', 'OUT', 'TRANSFER', 'RETURN'])) {
            return response()->json(['error' => 'Invalid types provided'], 422);
        }

        $movements = StockMovement::with(['movedBy:id,full_name', 'emplacement:id,code', 'to_emplacement:id,code', 'to_company'])
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

                        // attach article to new palette
                        $palette->articles()->attach($article->id, [
                            'quantity' => floatval($conditionMultiplier)
                        ]);
                    }
                } else {

                    // Find or create ONE palette for this emplacement
                    $palette = Palette::firstOrCreate(
                        ["emplacement_id" => $emplacement->id, "type" => "Stock"],
                        [
                            "code"       => $this->generatePaletteCode(),
                            "company_id" => $companyId,
                            "user_id"    => auth()->id()
                        ]
                    );

                    // check article exist inside palette
                    $existing = $palette->articles()->where('article_stock_id', $article->id)->first();

                    if ($existing) {
                        // UPDATE correctly
                        $palette->articles()->updateExistingPivot(
                            $article->id,
                            ['quantity' => DB::raw('quantity + ' . $qte_value)]
                        );
                    } else {
                        // INSERT normally
                        $palette->articles()->attach($article->id, ['quantity' => $qte_value]);
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


    public function stockInsert($emplacement, $article, $qte_value, $conditionMultiplier, $type_colis, $quantity)
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
            \Log::alert($emplacement);
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
