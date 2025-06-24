<?php

namespace App\Http\Controllers;

use App\Models\Emplacement;
use Illuminate\Http\Request;

class EmplacementController extends Controller
{
    public function show(Emplacement $emplacement)
    {
        $emplacement->load([
            'depot',
            'palettes' => function ($query) {
                $query->whereNull('inventory_id');
            },
            'palettes.articles'
        ]);

        return response()->json($emplacement);
    }


    
    public function showForInventory(Emplacement $emplacement)
    {
        $emplacement->load(['depot', 'palettes.inventoryArticles']);
        return response()->json($emplacement);
    }
}
