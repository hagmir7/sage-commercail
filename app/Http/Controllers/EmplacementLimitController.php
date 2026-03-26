<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Imports\EmplacementLimitImport;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class EmplacementLimitController extends Controller
{
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|extensions:xlsx,xls,csv'
        ]);

        try {
            Excel::import(new EmplacementLimitImport, $request->file('file'));

            return response()->json([
                'message' => 'Import terminé avec succès'
            ], 200);

        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Erreur lors de l\'import : ' . $e->getMessage()
            ], 500);
        }
    }
}