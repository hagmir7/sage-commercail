<?php

namespace App\Http\Controllers;

use App\Models\Emplacement;
use App\Models\Inventory;
use Illuminate\Http\Request;

class EmplacementController extends Controller
{
    public function show(Emplacement $emplacement)
    {
        $emplacement->load([
            'depot',
            'palettes' => function ($query) {
                $query->where('type', 'Stock');
            },
            'palettes.articles'
        ]);

        return response()->json($emplacement);
    }



    public function showForInventory(Emplacement $emplacement, Inventory $inventory)
    {
        $emplacement->load(['depot', 'palettes' => function ($query) use($inventory) {
            $query->where('inventory_id', $inventory->id );
        }, 'palettes.inventoryArticles']);
        return response()->json($emplacement);
    }

    public function create(Request $request){
        Emplacement::create([
            'depot_id' => $request->depot_id,
            'code' => $request->code
        ]);
        return response()->json(['message' => "Addedd successfully"] );
    }
}
