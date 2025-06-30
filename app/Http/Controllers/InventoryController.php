<?php

namespace App\Http\Controllers;

use App\Models\ArticleStock;
use App\Models\Depot;
use App\Models\Emplacement;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Palette;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use DateTime;
use Exception;
use Illuminate\Support\Facades\Log;

class InventoryController extends Controller
{

    public function list()
    {
        $inventories = Inventory::paginate(10);
        return $inventories;
    }


    public function show(Request $request, Inventory $inventory)
    {
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'dates' => 'nullable|string',
            'types' => 'nullable|string',
        ]);

        $types = $request->filled('types') ? explode(',', $request->types) : null;


        if ($types && array_diff($types, ['IN', 'OUT', 'TRANSFER'])) {
            return response()->json(['error' => 'Invalid types provided'], 422);
        }

        $movements = $inventory->movements()
            ->with(['user:id,full_name'])
            ->when($types, fn($q) => $q->whereIn('type', $types));

        if (!auth()->user()->hasRole('admin') || !auth()->user()->hasRole('supper_admin')) {
            $movements->where("user_id", auth()->id());
        }


        $depots = $request->filled('depots') ? explode(',', $request->depots) : null;

        $users = $request->filled('users') ? explode(',', $request->users) : null;


        if (!empty($depots)) {
            $movements->filterByDepots($depots);
        }

        if ($request->filled('emplacement')  && $request->search !== '') {
            $movements->filterByEmplacement($request->emplacement);
        }


        if (!empty($users)) {
            $movements->filterByUsers($users);
        }

        if ($request->filled('search')  && $request->search !== '') {
            $movements->search($request->search);
        }


        if ($request->filled('category') && $request->category !== '') {
            $movements->filterByCategory($request->category);
        }

        if ($request->filled('dates')  && $request->dates !== ',') {
            $movements->filterByDates($request->dates);
        }


        return response()->json([
            'inventory' => $inventory,
            'movements' => $movements->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 30)),
        ]);
    }



    public function create(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|min:3',
            'date' => 'date|required',
            'description' => 'string'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()]);
        }

        $inventory = Inventory::create([
            'name' => $request->name,
            'date' => $request->date,
            'description' => $request->description
        ]);

        return response()->json($inventory);
    }



    public function scanEmplacmenet($code)
    {
        $emplacement = Emplacement::with('depot.company')->where('code', $code)->first();

        if (!$emplacement) {
            return response()->json(['error' => "emplac emplacement is not Found"], 404);
        }
        return $emplacement;
    }



    public function scanArticle($code)
    {
        $article = ArticleStock::with('companies')->where('code', $code)
            ->orWhere('code_supplier', $code)
            ->orWhere('code_supplier_2', $code)
            ->orWhere('qr_code', $code)
            ->first();

        if (!$article) {
            return response()->json(['error' => "Article not found"], 404);
        }

        return $article;
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


public function insert(Request $request, Inventory $inventory)
{
    try {
        $validator = Validator::make($request->all(), [
            'emplacement_code' => 'required|string|max:255|min:3|exists:emplacements,code',
            'article_code' => 'string|required',
            'quantity' => 'numeric|required|min:0',
            'condition' => 'nullable',
            'type_colis' => 'nullable|in:Piece,Palette,Carton',
            'palettes' => 'numeric',
        ]);

        if ($validator->fails()) {
            Log::warning('Validation failed in insert function', [
                'errors' => $validator->errors()->toArray(),
                'request_data' => $request->all(),
                'inventory_id' => $inventory->id
            ]);
            
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $article = ArticleStock::where('code', $request->article_code)->first();

        if (!$article) {
            Log::warning('Article not found in insert function', [
                'article_code' => $request->article_code,
                'inventory_id' => $inventory->id
            ]);
            
            return response()->json([
                'errors' => ['article' => 'Article non trouvé']
            ], 404);
        }

        $inventory_stock = InventoryStock::where('code_article', $request->article_code)
            ->where('inventory_id', $inventory->id)
            ->first();

        $conditionMultiplier = $request->condition ? (float) $request->condition : 1.0;

        DB::transaction(function () use ($article, $request, $inventory, $inventory_stock, $conditionMultiplier) {
            try {
                $emplacement = Emplacement::where('code', $request->emplacement_code)->first();
                
                InventoryMovement::create([
                    'code_article' => $request->article_code,
                    'designation' => $article->description,
                    'emplacement_code' => $request->emplacement_code,
                    'emplacement_id' => $emplacement->id,
                    'inventory_id' => $inventory->id,
                    'type' => "IN",
                    'quantity' => $request->quantity,
                    'user_id' => auth()->id(),
                    'company_id' => intval($request?->company ?? 1),
                    'date' => now(),
                ]);

                if ($request->type_colis == "Palette" || $request->type_colis == "Carton") {
                    $qte_value = $request->palettes * $conditionMultiplier;
                } else {
                    $qte_value = $request->quantity;
                }

                if ($inventory_stock) {
                    $inventory_stock->update([
                        'quantity' => $inventory_stock->quantity + $qte_value,
                    ]);
                } else {
                    $inventory_stock = InventoryStock::create([
                        'code_article' => $request->article_code,
                        'designation' => $article->description,
                        'inventory_id' => $inventory->id,
                        'price' => $article->price,
                        'quantity' => $qte_value,
                    ]);
                }

                // Create Palette
                if ($request->type_colis == "Palette") {
                    for ($i = 1; $i <= intval($request->palettes); $i++) {
                        $palette = Palette::create([
                            "code" => $this->generatePaletteCode(),
                            "emplacement_id" => $emplacement->id,
                            "company_id" => intval($request?->company ?? 1),
                            "user_id" => auth()->id(),
                            "type" => "Inventaire",
                            "inventory_id" => $inventory?->id
                        ]);

                        $palette->inventoryArticles()->attach($inventory_stock->id, [
                            'quantity' => $request->condition
                        ]);
                    }
                } else {
                    $palette = Palette::firstOrCreate(
                        [
                            "emplacement_id" => $emplacement->id,
                            "inventory_id" => $inventory?->id
                        ],
                        [
                            "code" => $this->generatePaletteCode(),
                            "company_id" => intval($request?->company ?? 1),
                            "user_id" => auth()->id(),
                            "type" => "Inventaire"
                        ]
                    );

                    $existing = $palette->inventoryArticles()->where('code_article', $article->code)->first();

                    if ($existing) {
                        $currentQty = $existing->pivot->quantity;
                        $newQty = $currentQty + floatval($request->quantity);

                        $palette->inventoryArticles()->updateExistingPivot($inventory_stock->id, ['quantity' => $newQty]);
                    } else {
                        $palette->inventoryArticles()->attach($inventory_stock->id, [
                            'quantity' => floatval($request->quantity)
                        ]);
                    }
                }
            } catch (\Exception $transactionException) {
                Log::error('Error within database transaction in insert function', [
                    'error' => $transactionException->getMessage(),
                    'trace' => $transactionException->getTraceAsString(),
                    'request_data' => $request->all(),
                    'inventory_id' => $inventory->id,
                    'article_code' => $request->article_code
                ]);
                throw $transactionException; // Re-throw to trigger transaction rollback
            }
        });

        Log::info('Stock successfully inserted or updated', [
            'article_code' => $request->article_code,
            'inventory_id' => $inventory->id,
            'quantity' => $request->quantity,
            'user_id' => auth()->id()
        ]);

        return response()->json(['message' => 'Stock successfully inserted or updated.']);
        
    } catch (\Illuminate\Database\QueryException $e) {
        Log::error('Database error in insert function', [
            'error' => $e->getMessage(),
            'sql' => $e->getSql() ?? 'N/A',
            'bindings' => $e->getBindings() ?? [],
            'trace' => $e->getTraceAsString(),
            'request_data' => $request->all(),
            'inventory_id' => $inventory->id
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
            'inventory_id' => $inventory->id,
            'line' => $e->getLine(),
            'file' => $e->getFile()
        ]);
        
        return response()->json([
            'message' => 'Une erreur inattendue s\'est produite.',
            'error' => 'Internal server error'
        ], 500);
    }
}

    public function movements(Inventory $inventory)
    {
        return $inventory->with('movements');
    }


    public function deleteMovement(InventoryMovement $inventory_movement)
    {
        try {
            DB::transaction(function () use ($inventory_movement) {
                $inventory_stock = InventoryStock::where("code_article", $inventory_movement->code_article)
                    ->first();

                if ($inventory_stock) {
                    $inventory_stock->update([
                        'quantity' => $inventory_stock->quantity - $inventory_movement->quantity
                    ]);
                    $inventory_movement->delete();
                } else {
                    throw new \Exception('Inventory stock not found for article: ' . $inventory_movement->code_article);
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Movement deleted successfully',
                'data' => [
                    'movement_id' => $inventory_movement->id,
                    'code_article' => $inventory_movement->code_article
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete movement',
                'error' => $e->getMessage()
            ], 400);
        }
    }


    public function stockArticle(Request $request, Inventory $inventory)
    {

        $query = ArticleStock::query()
            ->leftJoin('inventory_stocks', function ($join) use ($inventory) {
                $join->on('article_stocks.code', '=', 'inventory_stocks.code_article')
                    ->where('inventory_stocks.inventory_id', '=', $inventory->id);
            })
            ->select(
                'article_stocks.*',
                'inventory_stocks.quantity as inventory_quantity',
            );

        $query->orderByDesc("inventory_quantity");

        // Apply filters
        if ($request->has('category')) {
            $query->where('article_stocks.category', $request->category);
        }

        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('article_stocks.code', 'like', "%$search%")
                    ->orWhere('article_stocks.name', 'like', "%$search%")
                    ->orWhere('article_stocks.description', 'like', "%$search%")
                    ->orWhere('article_stocks.color', 'like', "%$search%");
            });
        }

        $articles = $query->paginate(100);

        return response()->json($articles);
    }


    public function overview(Inventory $inventory)
    {
        $inventory->load(['movements.article', 'stock.article']);
        $quantity_in = $inventory->movements->where('type', 'IN')->sum('quantity');
        $quantity_out = $inventory->movements->where('type', 'OUT')->sum('quantity');

        $value_in = $inventory->movements->where('type', 'IN')->sum(function ($movement) {
            return $movement->quantity * ($movement->article->price ?? 0);
        });

        $value_out = $inventory->movements->where('type', 'OUT')->sum(function ($movement) {
            return $movement->quantity * ($movement->article->price ?? 0);
        });

        $quantity = $inventory->stock->sum('quantity');

        $value = $inventory->stock->sum(function ($stock) {
            return $stock->quantity * ($stock->article->price ?? 0);
        });

        return [
            'quantity_in' => $quantity_in,
            'quantity_out' => $quantity_out,
            'value_in' => $value_in,
            'value_out' => $value_out,
            'quantity' => $quantity,
            'value' => $value
        ];
    }


    public function depotEmplacements(Inventory $inventory, Depot $depot)
    {
        $raw = DB::table('emplacements')
            ->leftJoin('palettes', function ($join) use ($inventory) {
                $join->on('emplacements.id', '=', 'palettes.emplacement_id')
                    ->where('palettes.inventory_id', '=', $inventory->id);
            })
            ->leftJoin('inventory_article_palette', 'palettes.id', '=', 'inventory_article_palette.palette_id')
            ->select(
                'emplacements.id as emplacement_id',
                'emplacements.code',
                'palettes.id as palette_id',
                'inventory_article_palette.inventory_stock_id',
                'inventory_article_palette.quantity'
            )
            ->where('emplacements.depot_id', $depot->id)
            ->get();

        $grouped = $raw->groupBy('emplacement_id')->map(function ($rows, $emplacementId) {
            $code = $rows->first()->code;

            $uniquePaletteCount = $rows->pluck('palette_id')->filter()->unique()->count();

            $articleQuantities = $rows
                ->filter(fn($row) => $row->inventory_stock_id !== null)
                ->groupBy('inventory_stock_id')
                ->map(fn($articles) => $articles->sum('quantity'));

            return [
                'id' => $emplacementId,
                'code' => $code,
                'palette_count' => $uniquePaletteCount,
                'distinct_article_count' => $articleQuantities->count(),
                'total_articles_quantity' => $articleQuantities->sum(),
                'articles' => $articleQuantities,
            ];
        })->values();

        return response()->json([
            "depot" => $depot,
            "emplacements" => $grouped
        ]);
    }



    public function updateArticleQuantityInPalette(Request $request, Palette $palette, InventoryStock $inventory_stock)
    {
        try {
            $validator = Validator::make($request->all(), [
                'quantity' => "numeric|required|min:0.001"
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => "La quantité n'est pas valide",
                    'errors' => $validator->errors()
                ], 422);
            }

            $newQuantity = $request->quantity;

            $currentPivotData = DB::selectOne(
                "SELECT quantity FROM inventory_article_palette WHERE inventory_stock_id = ? AND palette_id = ?",
                [$inventory_stock->id, $palette->id]
            );

            if (!$currentPivotData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Article non trouvé dans cette palette'
                ], 404);
            }

            $oldQuantity = $currentPivotData->quantity;
            $quantityDifference = $newQuantity - $oldQuantity;

            // Skip update if no change
            if ($quantityDifference == 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Aucune modification nécessaire',
                    'data' => [
                        'old_quantity' => $oldQuantity,
                        'new_quantity' => $newQuantity,
                        'quantity_difference' => $quantityDifference,
                        'inventory_updated' => false
                    ]
                ], 200);
            }

            DB::transaction(function () use ($inventory_stock, $quantityDifference, $palette, $newQuantity) {
                if ($quantityDifference > 0) {

                    $inventory_stock->update([
                        'quantity' => $inventory_stock->quantity + $quantityDifference
                    ]);
                } elseif ($quantityDifference < 0) {
                    $inventory_stock->update([
                        'quantity' => $inventory_stock->quantity - abs($quantityDifference)
                    ]);
                }

                $palette->inventoryArticles()->updateExistingPivot($inventory_stock->id, [
                    'quantity' => $newQuantity
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Quantité mise à jour avec succès',
                'data' => [
                    'old_quantity' => $oldQuantity,
                    'new_quantity' => $newQuantity,
                    'quantity_difference' => $quantityDifference,
                    'inventory_updated' => true
                ]
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Model not found in updateArticleQuantity: ' . $e->getMessage(), [
                'palette_id' => $palette->id,
                'article_id' => $inventory_stock->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Palette ou article non trouvé'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error updating article quantity: ' . $e->getMessage(), [
                'palette_code' => $palette->code,
                'article_id' => $inventory_stock->id,
                'quantity' => $request->quantity ?? null
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la quantité'
            ], 500);
        }
    }

    public function deleteArticleFromPalette(Request $request, Palette $palette, InventoryStock $inventory_stock)
    {
        try {
            $currentPivotData = DB::selectOne(
                "SELECT quantity FROM inventory_article_palette WHERE inventory_stock_id = ? AND palette_id = ?",
                [$inventory_stock->id, $palette->id]
            );

            if (!$currentPivotData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Article non trouvé dans cette palette'
                ], 404);
            }

            $quantityToRemove = $currentPivotData->quantity;

            DB::transaction(function () use ($inventory_stock, $palette, $quantityToRemove) {
                // Decrease inventory stock
                $inventory_stock->update([
                    'quantity' => $inventory_stock->quantity - $quantityToRemove
                ]);

                // Detach the relation
                $palette->inventoryArticles()->detach($inventory_stock->id);
            });

            return response()->json([
                'success' => true,
                'message' => 'Article supprimé de la palette avec succès',
                'data' => [
                    'removed_quantity' => $quantityToRemove
                ]
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Model not found in deleteArticleFromPalette: ' . $e->getMessage(), [
                'palette_id' => $palette->id,
                'article_id' => $inventory_stock->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Palette ou article non trouvé'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error deleting article from palette: ' . $e->getMessage(), [
                'palette_code' => $palette->code,
                'article_id' => $inventory_stock->id
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de l’article de la palette'
            ], 500);
        }
    }


    public function resetToStock(Inventory $inventory)
    {
        try {
            DB::transaction(function () use ($inventory) {

                Palette::where('type', 'Stock')
                    ->whereNull('inventory_id')
                    ->delete();


                Palette::where('type', 'Stock')
                    ->whereNotNull('inventory_id')
                    ->update(['type' => 'Inventaire']);


                foreach ($inventory->palettes as $palette) {

                    $palette->update(['type' => 'Stock']);

                    $articlesToAttach = [];

                    foreach ($palette->inventoryArticles as $inventoryArticle) {
                        $article = ArticleStock::where('code', $inventoryArticle->code_article)->first();

                        if ($article) {
                            $articlesToAttach[$article->id] = [
                                'quantity' => $inventoryArticle->pivot->quantity,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    }

                    if (!empty($articlesToAttach)) {
                        $palette->articles()->attach($articlesToAttach);
                    }
                }
            });


            return response()->json([
                'success' => true,
                'message' => 'Stock initialized successfully',
            ]);
        } catch (\Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize stock: ' . $e->getMessage(),
            ], 500);
        }
    }



    public function controlle(InventoryMovement $inventory_movement){
        
        $inventory_movement->update([
            'controlled_by' => auth()->id()
        ]);

        return response()->json(['message' => 'Mouvement contrôlé avec succès']);

    }
}
