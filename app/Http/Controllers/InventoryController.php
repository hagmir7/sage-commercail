<?php

namespace App\Http\Controllers;

use App\Models\ArticleStock;
use App\Models\Emplacement;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use SebastianBergmann\CodeUnit\FunctionUnit;
use DateTime;
use Exception;

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


        if ($request->filled('search') && $request->search !== '') {
            $search = $request->search;
            $movements->where(function ($q) use ($search) {
                $q->where('emplacement_code', 'like', "%$search%")
                    ->orWhere('code_article', 'like', "%$search%")
                    ->orWhere('designation', 'like', "%$search%");
            });
        }

        // Date Filter
        if ($request->filled('dates') && $request->dates !== ',') {
            $dates = array_map('trim', explode(',', $request->dates));

            if (count($dates) === 2) {
                try {
                    $start = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dates[0])
                        ? $dates[0] . ' 00:00:00'
                        : DateTime::createFromFormat('d/m/Y', $dates[0])->format('Y-m-d 00:00:00');

                    $end = preg_match('/^\d{4}-\d{2}-\d{2}$/', $dates[1])
                        ? $dates[1] . ' 23:59:59'
                        : DateTime::createFromFormat('d/m/Y', $dates[1])->format('Y-m-d 23:59:59');

                    $movements->whereBetween('created_at', [$start, $end]);
                } catch (Exception $e) {
                    \Log::error('Date parsing error: ' . $e->getMessage());
                }
            }
        }

        return response()->json([
            'inventory' => $inventory,
            'movements' => $movements->orderBy('created_at', 'desc')
                ->paginate($request->input('per_page', 15)),
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
        $emplacement = Emplacement::with('depte.company')->where('code', $code)->first();

        if (!$emplacement) {
            return response()->json(['error' => "emplac emplacement is not Found"], 404);
        }
        return $emplacement;
    }



    public function scanArticle($code)
    {
        $article = ArticleStock::where('code', $code)->first();

        if (!$article) {
            return response()->json(['error' => "Article is not Found"], 404);
        }
        return $article;
    }





    public function insert(Request $request, Inventory $inventory)
    {
        $validator = Validator::make($request->all(), [
            'emplacement_code' => 'required|string|max:255|min:3|exists:emplacements,code',
            'article_code' => 'string|required',
            'quantity' => 'integer|required|min:0',
            'condition' => 'nullable'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $article = ArticleStock::where('code', $request->article_code)->first();

        if (!$article) {
            return response()->json([
                'errors' => ['article' => 'Article non trouvé']
            ], 404);
        }

        $inventory_stock = InventoryStock::where('code_article', $request->article_code)
            ->where('inventory_id', $inventory->id)
            ->first();

        $conditionMultiplier = $request->condition ? (int) $request->condition : 1;


        DB::transaction(function () use ($article, $request, $inventory, $inventory_stock, $conditionMultiplier) {
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
                'date' => now(),
            ]);

            if ($inventory_stock) {
                $inventory_stock->update([
                    'quantity' => $inventory_stock->quantity + ($conditionMultiplier * $request->quantity),
                ]);
            } else {
                InventoryStock::create([
                    'code_article' => $request->article_code,
                    'designation' => $article->description,
                    'inventory_id' => $inventory->id,
                    'price' => $article->price,
                    'quantity' => $conditionMultiplier * $request->quantity,
                ]);
            }
        });

        return response()->json(['message' => 'Stock successfully inserted or updated.']);
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
        if ($request->has('family_id')) {
            $query->where('article_stocks.family_id', $request->family_id);
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

        $articles = $query->with('family')->paginate(100);

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


}
