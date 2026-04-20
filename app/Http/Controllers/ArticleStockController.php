<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Imports\ArticleStockImport;
use App\Models\ArticleStock;
use App\Models\Docligne;
use App\Models\Emplacement;
use App\Models\EmplacementLimit;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ArticleStockController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = ArticleStock::query();

        // ── Filters ───────────────────────────────────────────────────────────────
        if ($request->filled('category') && $request->category !== 'tout') {
            $query->where('category', $request->category);
        }
        if ($request->filled('color') && $request->color !== 'tout') {
            $query->where('color', $request->color);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('code_supplier', 'like', "%{$search}%")
                    ->orWhere('code_supplier_2', 'like', "%{$search}%")
                    ->orWhere('color', 'like', "%{$search}%");
            });
        }

        // ── DB-level sort (only for real DB columns) ──────────────────────────────
        $allowedDbSorts = ['code', 'name', 'stock_min'];
        $computedSorts  = ['urgency_level', 'stock', 'ecart'];
        $sortBy         = $request->get('sort_by', 'code');
        $sortOrder      = $request->get('sort_order', 'asc') === 'desc' ? 'desc' : 'asc';

        if (in_array($sortBy, $allowedDbSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $company = $request->filled('company') ? $request->company : null;

        // ── Fetch ALL matching records (no paginate yet) ──────────────────────────
        $all  = $query->get();
        $ids  = $all->pluck('id')->all();
        $codes = $all->pluck('code')->all();

        // ══════════════════════════════════════════════════════════════════════════
        // BATCH QUERY 1 — Stock per article
        // ══════════════════════════════════════════════════════════════════════════
        $stockQuery = DB::table('article_palette as ap')
            ->join('palettes as p', 'p.id', '=', 'ap.palette_id')
            ->where('p.type', 'Stock')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('emplacements as e')
                    ->whereColumn('e.id', 'p.emplacement_id')
                    ->whereIn('e.code', ['K-3P', 'K-4P', 'K-4SP', 'K-3SP']);
            })
            ->whereIn('ap.article_stock_id', $ids);

        if ($company) {
            $stockQuery->where('p.company_id', $company);
        }

        $stockMap = $stockQuery
            ->groupBy('ap.article_stock_id')
            ->select('ap.article_stock_id', DB::raw('SUM(ap.quantity) as total'))
            ->pluck('total', 'ap.article_stock_id');

        // ══════════════════════════════════════════════════════════════════════════
        // BATCH QUERY 2 — EmplacementLimit totals
        // ══════════════════════════════════════════════════════════════════════════
        $limitMap = EmplacementLimit::whereIn('article_stock_id', $ids)
            ->groupBy('article_stock_id')
            ->selectRaw('article_stock_id, SUM(quantity) as total')
            ->pluck('total', 'article_stock_id');

        // ══════════════════════════════════════════════════════════════════════════
        // BATCH QUERY 3 — Stock preparation
        // ══════════════════════════════════════════════════════════════════════════
        $prepMap = Docligne::whereIn('AR_Ref', $codes)
            ->whereIn('DO_Type', [1, 2, 3])
            ->whereColumn('DL_Qte', '>', 'DL_QteBL')
            ->groupBy('AR_Ref')
            ->selectRaw('AR_Ref, SUM(DL_Qte) as total')
            ->pluck('total', 'AR_Ref');

        // ══════════════════════════════════════════════════════════════════════════
        // BATCH QUERY 4 — Zone preparation
        // ══════════════════════════════════════════════════════════════════════════
        $zoneMap = Docligne::whereIn('AR_Ref', $codes)
            ->whereIn('DO_Type', [1, 2, 3])
            ->groupBy('AR_Ref')
            ->selectRaw('AR_Ref, SUM(DL_QteBL) as total')
            ->pluck('total', 'AR_Ref');

        // ══════════════════════════════════════════════════════════════════════════
        // ENRICH — O(n) map lookups, zero extra queries
        // ══════════════════════════════════════════════════════════════════════════
        $all->transform(function ($article) use ($stockMap, $limitMap, $prepMap, $zoneMap) {
            $article->stock_prepare    = (float) ($prepMap[$article->code]  ?? 0);
            $article->stock_prepartion = (float) ($zoneMap[$article->code]  ?? 0);
            $article->stock            = (float) ($stockMap[$article->id]   ?? 0);
            $article->max              = (float) ($limitMap[$article->id]   ?? 0);

            $stock   = floor($article->stock * 100) / 100;
            $max     = $article->max;
            $min     = (float) $article->stock_min;
            $moyenne = $max / 2;

            $article->urgency_level = match (true) {
                $stock < $min                           => 1,
                $stock >= $min && $stock < $moyenne     => 2,
                $stock >= $moyenne && $stock < $max     => 3,
                default                                 => 4,
            };

            $article->ecart = $max - $stock;

            return $article;
        });

        // ── Sort by computed field (across ALL records) ───────────────────────────
        if (in_array($sortBy, $computedSorts)) {
            $all = $all->sortBy(
                fn($a) => $a->{$sortBy},
                SORT_REGULAR,
                $sortOrder === 'desc'
            )->values();
        }

        // ── Urgency filter (post-enrichment) ──────────────────────────────────────
        if ($request->filled('urgency') && $request->urgency !== 'tout') {
            $level = (int) $request->urgency;
            $all   = $all->filter(fn($a) => $a->urgency_level === $level)->values();
        }

        // ── Manual pagination over the fully sorted+filtered collection ───────────
        $perPage     = (int) $request->get('per_page', 100);
        $currentPage = (int) $request->get('page', 1);
        $total       = $all->count();

        $pageItems = $all->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $pageItems,
            $total,
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json($paginator);
    }

    public function list(Request $request)
    {
        $query = ArticleStock::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        return $query->paginate(50);
    }



    public function calculateStock($ref_article, $company_id = null)
    {
        $article = ArticleStock::where('code', $ref_article)->first();

        if (! $article) {
            return 0;
        }

        $query = $article->palettes()
            ->where('type', 'Stock')
            ->whereDoesntHave('emplacement', function ($q) {
                $q->whereIn('code', ['K-3P', 'K-4P', 'K-4SP', 'K-3SP']);
            });

        if ($company_id) {
            $query->where('company_id', $company_id);
        }

        return $query->sum('article_palette.quantity');
    }

    public function calculateLimit($article_id)
    {
        return EmplacementLimit::where('article_stock_id', $article_id)
            ->sum('quantity');
    }




    public function calculateStockPreparation($ref_article)
    {

        return Docligne::where('AR_Ref', $ref_article)
            ->whereIn('DO_Type', [2, 3, 1])
            ->whereColumn("DL_Qte", '>', 'DL_QteBL')
            ->sum('DL_Qte');
    }

    public function calculateZoonPrepartion($ref_article)
    {

        return Docligne::where('AR_Ref', $ref_article)
            ->whereIn('DO_Type', [2, 3, 1])
            ->sum('DL_QteBL');
    }




    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        try {
            ini_set('max_execution_time', 7200); // 2 hours
            ini_set('memory_limit', '4G'); // 1GB memory

            Excel::import(new ArticleStockImport, $request->file('file'));

            return response()->json([
                'message' => "Fichier importé avec succès"
            ], 200);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->failures()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Import failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Erreur lors de l\'importation du fichier'
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        if (!auth()->user()->hasRole('admin')) {
            return response()->json(['message' => "Vous n'êtes pas authentifié ⚠️"], 402);
        }

        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:100|unique:article_stocks,code,except,code',
            'description' => 'nullable|string|max:255',
            'name' => 'string|max:150',
            'color' => 'nullable|string|max:50',
            'quantity' => 'nullable|numeric',
            'stock_min' => 'nullable|numeric',
            'price' => 'nullable|numeric',
            'thickness' => 'nullable|numeric',
            'height' => 'nullable|numeric',
            'width' => 'nullable|numeric',
            'depth' => 'nullable|numeric',
            'chant' => 'nullable|string|max:100',
            'condition' => 'nullable|string|max:100',
            'code_supplier' => 'nullable|string|max:100',
            'qr_code' => 'nullable|string|max:255',
            'palette_condition' => 'nullable|string|max:100',
            'unit' => 'nullable|string|max:20',
            'gamme' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update article fields
        $article_stock = ArticleStock::create($request->only([
            'code',
            'description',
            'name',
            'color',
            'qte_inter',
            'qte_serie',
            'quantity',
            'stock_min',
            'price',
            'thickness',
            'height',
            'width',
            'depth',
            'chant',
            'condition',
            'code_supplier',
            'qr_code',
            'palette_condition',
            'unit',
            'gamme',
            'category'
        ]));

        return response()->json([
            'message' => 'Article créé avec succès.',
            'id' => $article_stock->code
        ], 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(ArticleStock $article_stock)
    {
        return $article_stock;
        // return response()->json(['data' => ]);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    public function update(Request $request, ArticleStock $article_stock)
    {
        if (!auth()->user()->hasRole('admin')) {
            return response()->json(['message' => "Vous n'êtes pas authentifié ⚠️"], 402);
        }



        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'name' => 'string|max:150',
            'color' => 'nullable|string|max:50',
            'qte_inter' => 'nullable|numeric',
            'qte_serie' => 'nullable|numeric',
            'quantity' => 'nullable|numeric',
            'stock_min' => 'nullable|numeric',
            'price' => 'nullable|numeric',
            'thickness' => 'nullable|numeric',
            'height' => 'nullable|numeric',
            'width' => 'nullable|numeric',
            'depth' => 'nullable|numeric',
            'chant' => 'nullable|string|max:100',
            'condition' => 'nullable|string|max:100',
            'code_supplier' => 'nullable|string|max:100',
            'qr_code' => 'nullable|string|max:255',
            'palette_condition' => 'nullable|string|max:100',
            'unit' => 'nullable|string|max:20',
            'gamme' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }


        if (!$article_stock) {
            return response()->json(['message' => 'Article introuvable.'], 404);
        }

        // Update article fields
        $article_stock->update($request->only([
            'code',
            'description',
            'name',
            'color',
            'qte_inter',
            'qte_serie',
            'quantity',
            'stock_min',
            'price',
            'thickness',
            'height',
            'width',
            'depth',
            'chant',
            'condition',
            'code_supplier',
            'qr_code',
            'palette_condition',
            'unit',
            'gamme',
            'category'
        ]));

        return response()->json([
            'message' => 'Article updated successfully.',
            'data' => $article_stock
        ], 200);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(ArticleStock $article_stock)
    {
        if (!auth()->user()->hasRole('admin')) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }
        $article_stock->delete();

        return response()->json([
            'message' => 'Article deleted successfully'
        ], 200);
    }

    public function emplacements(string $code)
    {
        $article = ArticleStock::with('companies')
            ->where(function ($q) use ($code) {
                $q->where('code', $code)
                    ->orWhere('code_supplier', $code)
                    ->orWhere('code_supplier_2', $code)
                    ->orWhere('qr_code', $code);
            })
            ->first();

        if (!$article) {
            return response()->json(['message' => 'Article introuvable.'], 404);
        }

        $emplacements = Emplacement::whereHas('palettes.articles', function ($q) use ($article) {
            $q->where('article_stocks.id', $article->id);
        })
            ->with([
                'depot.company',

                'palettes' => function ($q) use ($article) {
                    $q->where('type', 'Stock')
                        ->whereHas(
                            'articles',
                            fn($a) =>
                            $a->where('article_stocks.id', $article->id)
                        )
                        ->with([
                            'articles' => fn($a) =>
                            $a->where('article_stocks.id', $article->id)
                                ->withPivot('quantity')
                        ]);
                }
            ])
            ->get();

        return response()->json($emplacements);
    }




    public function decrementQuantity($code, $quantity)
    {
        // 1) Find Article
        $article = ArticleStock::with('companies')->where('code', $code)
            ->orWhere('code_supplier', $code)
            ->orWhere('code_supplier_2', $code)
            ->orWhere('qr_code', $code)
            ->first();

        if (!$article) {
            return response()->json(['message' => 'Article introuvable.'], 404);
        }

        // 2) Get the FIRST emplacement where article exists (ONLY ONE!)
        $emplacement = Emplacement::whereHas('palettes.articles', function ($query) use ($article) {
            $query->where('article_stocks.id', $article->id)->where("type", 'Stock');
        })
            ->with([
                'depot.company',
                'palettes' => function ($q) use ($article) {
                    $q->whereHas('articles', fn($a) => $a->where('article_stocks.id', $article->id))
                        ->with(['articles' => fn($a) => $a->where('article_stocks.id', $article->id)]);
                }
            ])
            ->orderBy('id', 'ASC')
            ->first();

        if (!$emplacement) {
            return response()->json(['message' => 'Aucun emplacement trouvé'], 404);
        }

        // 3) Find article in the palette (pivot row which contains quantity)
        $palette = $emplacement->palettes->first();
        $paletteArticle = $palette->articles()->where('article_stocks.id', $article->id)->first();

        if (!$paletteArticle) {
            return response()->json(['message' => 'Article non trouvé dans palette'], 404);
        }

        // 4) Decrement quantity
        $currentQty = $paletteArticle->pivot->quantity;

        if ($quantity > $currentQty) {
            return response()->json(['message' => "Stock insuffisant. Disponible: $currentQty"], 400);
        }

        $palette->articles()->updateExistingPivot($article->id, [
            'quantity' => $currentQty - $quantity
        ]);

        return response()->json([
            'message' => 'Quantité mise à jour avec succès',
            'emplacement' => $emplacement,
            'quantity_before' => $currentQty,
            'quantity_after' => $currentQty - $quantity
        ]);
    }

    public function search(Request $request)
    {
        $query = $request->input('q');

        $articles = ArticleStock::select('code', 'description')
            ->when($query, function ($q) use ($query) {
                $q->where(function ($subQuery) use ($query) {
                    $subQuery->where('code', 'like', $query . '%')
                        ->orWhere('description', 'like', '%' . $query . '%');
                });
            })
            ->paginate(100);

        return response()->json($articles);
    }



    public function stock(Request $request)
    {
        $search         = $request->input('search');
        $depotCodes     = $request->input('depot_code', []);
        $category       = $request->input('category');
        $emplacement    = $request->input('emplacement');

        $excludedEmplacements = ['K-4P', 'K-3P', 'K-4SP', 'K-2SP'];

        $query = DB::table('emplacements as e')
            ->join('palettes as p', 'p.emplacement_id', '=', 'e.id')
            ->join('article_palette as ap', 'ap.palette_id', '=', 'p.id')
            ->join('article_stocks as a', 'a.id', '=', 'ap.article_stock_id')
            ->join('depots as d', 'd.id', '=', 'e.depot_id')
            ->leftJoin('emplacement_limit as el', function ($join) {
                $join->on('el.emplacement_id', '=', 'e.id')
                    ->on('el.article_stock_id', '=', 'a.id');
            })
            ->select([
                'e.id as emplacement_id',
                'e.code as emplacement_code',

                'a.id as article_stock_id',
                'a.code as article_code',
                'a.code_supplier',
                'a.category',
                'a.name',
                'a.description',
                'a.width',
                'a.height',
                'a.depth',
                'a.thickness',

                DB::raw('SUM(ap.quantity) as total_quantity'),
                'el.quantity as quantity_limit',
            ])
            ->where('p.type', '=', 'STOCK')
            ->where('a.category', '=', 'semi-fini')
            ->whereNotIn('e.code', $excludedEmplacements);

        /* =======================
    SEARCH
    ======================= */
        if (!empty($search)) {
            $query->where(function ($q) use ($search, $excludedEmplacements) {
                $q->where('a.code', 'like', "%{$search}%")
                    ->orWhere('a.code_supplier', 'like', "%{$search}%")
                    ->orWhere('a.name', 'like', "%{$search}%")
                    ->orWhere('a.description', 'like', "%{$search}%")
                    ->orWhere(function ($q2) use ($search, $excludedEmplacements) {
                        $q2->where('e.code', 'like', "%{$search}%")
                            ->whereNotIn('e.code', $excludedEmplacements);
                    });
            });
        }

        /* =======================
    FILTER BY DEPOT (multiple)
    ======================= */
        if (!empty($depotCodes)) {
            if (is_string($depotCodes)) {
                $depotCodes = explode(',', $depotCodes);
            }
            $depotCodes = array_filter(array_map('trim', (array) $depotCodes));
            if (count($depotCodes)) {
                $query->whereIn('d.code', $depotCodes);
            }
        }

        /* =======================
    FILTER BY EMPLACEMENT
    ======================= */
        if (!empty($emplacement)) {
            $query->where('e.code', 'like', "%{$emplacement}%");
        }

        /* =======================
    FILTER BY CATEGORY (STRING)
    ======================= */
        if (!empty($category)) {
            $query->where('a.category', $category);
        }

        /* =======================
    GROUPBY
    ======================= */
        $query->groupBy(
            'e.id',
            'e.code',
            'a.id',
            'a.code',
            'a.code_supplier',
            'a.category',
            'a.name',
            'a.description',
            'a.width',
            'a.height',
            'a.depth',
            'a.thickness',
            'el.quantity'
        );

        /* =======================
    ORDER & PAGINATION
    ======================= */
        return $query
            ->orderBy('e.code')
            ->orderBy('a.code')
            ->paginate(200);
    }
}
