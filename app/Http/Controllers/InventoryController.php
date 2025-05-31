<?php

namespace App\Http\Controllers;

use App\Models\ArticleStock;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InventoryController extends Controller
{
    public function store(Request $request)
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


    public function insert(Request $request, Inventory $inventory)
    {

        $validator = Validator::make($request->all(), [
            'emplacement_id' => 'required|string|max:255|min:3|exists:emplacements,id',
            'article_code' => 'string|required',
            'quantity' => 'integer|required|min:0',
            'condition' => 'nullable'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()]);
        }

        $article = ArticleStock::where('code', $request->article_code)->first();

        if (!$article) {
            return response()->json(['errors' => ['article' => 'Article non trouvé']], 404);
        }

        $inventory_stock = InventoryStock::where('code_article', $request->article_code)
            ->where('inventory_id', $inventory->id)
            ->first();


        DB::transaction(function () use($article, $request, $inventory, $inventory_stock) {
            InventoryMovement::create([
                "code_article" => 'article_code',
                'designation' => $article->description,
                'emplacement_id' => $request->emplacement_id,
                'inventory_id' => $inventory->id,
                'type' => "IN",
                'quantity' => $request->quantity,
                'user_id' => auth()->id(),
                'date' => now(),
            ]);


            if ($inventory_stock) {
                $inventory_stock->update([
                    'quantity' => (($request->condition || 1) * $request->quantity) + $inventory_stock->quantity,
                ]);
            } else {
                $stock = InventoryStock::create([
                    'code_article' => $request->article_code,
                    'designation' => $article->description,
                    'inventory_id' => $inventory->id,
                    'price' => $article->price,
                    'quantity' => ($request->condition || 1) * $request->quantity,
                ]);
            }
        });
    }


}
