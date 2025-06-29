<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class InventoryMovementController extends Controller
{

    public function updateQuantity(Request $request, InventoryMovement $inventory_movement)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => "required|numeric|min:0.1"
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        if ($inventory_movement->quantity != $request->quantity) {
            $inventory_movement->update(['quantity' => $request->quantity]);

            // Log pour audit
            \Log::info("Quantité mise à jour", [
                'movement_id' => $inventory_movement->id,
                'ancien' => $inventory_movement->quantity,
                'nouveau' => $request->quantity,
                'user_id' => auth()->id() ?? null
            ]);
        }

        return response()->json(['message' => "Quantité modifiée avec succès"]);
    }
}
