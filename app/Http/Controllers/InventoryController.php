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

class InventoryController extends Controller
{

    public function list(){
        $inventories = Inventory::paginate(10);
        return $inventories;
    }


    public function show(InventoryStock $inventory){
        return $inventory;
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



    public function scanEmplacmenet($code){
        $emplacement = Emplacement::with('depte.company')->where('code', $code)->first();

        if(!$emplacement){
            return response()->json(['error' => "emplac emplacement is not Found"], 404);
        }
        return $emplacement;

    }



    public function scanArticle($code){
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
}
