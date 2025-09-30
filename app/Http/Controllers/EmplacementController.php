<?php

namespace App\Http\Controllers;

use App\Imports\EmplacementImport;
use App\Models\Depot;
use App\Models\Emplacement;
use App\Models\Inventory;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class EmplacementController extends Controller
{
    public function show(Emplacement $emplacement)
    {
        if(!$emplacement){
            return response()->json(["message" => "Emplacement not found"]);
        }
        $emplacement->load([
            'depot',
            'palettes' => function ($query) {
                $query->where('type', 'Stock');
            },
            'palettes.articles'
        ]);

        return response()->json($emplacement);
    }

    public function create(Request $request)
    {
        Emplacement::create([
            'depot_id' => $request->depot_id,
            'code' => $request->code
        ]);
        return response()->json(['message' => "Addedd successfully"]);
    }

    public function delete(Emplacement $emplacement)
    {
        if (auth()->user()->hasRole(['admin', 'super_admin'])) {
            $emplacement->delete();
            return response()->json(["message" => "Emplacement deleted successfully"]);
        }

        return response()->json(["message" => "You are not authenticated ⚠️"], 401);
    }


    public function showForInventory(Emplacement $emplacement, Inventory $inventory)
    {
        $emplacement->load(['depot', 'palettes' => function ($query) use($inventory) {
            $query->where('inventory_id', $inventory->id );
        }, 'palettes.inventoryArticles']);
        return response()->json($emplacement);
    }

    public function import(Request $request, Depot $depot)
        {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv',
            ]);

            try {
                ini_set('max_execution_time', 7200); // 2 hours
                ini_set('memory_limit', '4G');

                Excel::import(new EmplacementImport($depot->id), $request->file('file'));

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
}
