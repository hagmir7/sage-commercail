<?php

namespace App\Http\Controllers;

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
            'emplacement' => 'required|exists:emplacements,code'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        if (($inventory_movement->quantity != $request->quantity) || ($inventory_movement->emplacement_code != $request->emplacement)) {
            $emplacement = Emplacement::where('code', $request->emplacement)->first();

            $inventory_movement->update([
                'quantity' => $request->quantity,
                'emplacement_id' => $emplacement->id,
                'emplacement_code' => $request->emplacement
            ]);

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
