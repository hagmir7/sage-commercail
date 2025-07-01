<?php

namespace App\Http\Controllers;

use App\Models\ArticleStock;
use App\Models\Emplacement;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InventoryMovementController extends Controller
{

    public function updateQuantity(Request $request, InventoryMovement $inventory_movement)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => "required|numeric|min:0.1",
            'code_article' => "exists:article_stocks,code",
            'emplacement' => 'required|exists:emplacements,code'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $article = ArticleStock::where('code', $request->code_article)->first();
        $emplacement = Emplacement::where('code', $request->emplacement)->first();

        $olde_quantity = $inventory_movement->quantity;
        $inventory_movement->update([
            'quantity' => $request->quantity,
            'emplacement_id' => $emplacement->id,
            'emplacement_code' => $request->emplacement,
            'code_article' => $request->code_article ? $request->code_article : $inventory_movement->code_article,
            'designation' => $request->code_article ? $article->description : $inventory_movement->designation
        ]);

        \Log::info("Quantité mise à jour", [
            'movement_id' => $inventory_movement->id,
            'ancien' =>  $olde_quantity,
            'nouveau' => $request->quantity,
            'user_id' => auth()->id() ?? null
        ]);
        return response()->json(['message' => "Quantité modifiée avec succès"]);
    }
}
