<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ValidationController extends Controller
{



    // Helper methods that need to be implemented based on your original C# methods:

    private function getFourniss(string $nPiece): array
    {
        // Implement this method to return array of suppliers
        // Example:
        return DB::table('fournisseurs_table')
            ->where('piece_number', $nPiece)
            ->pluck('fournisseur_name')
            ->toArray();
    }

    private function genererNouvelleReference(): string
    {
        // Implement your reference generation logic
        // Example:
        return 'REF_' . time() . '_' . rand(1000, 9999);
    }

    private function createDoc(string $newRefGen, string $refer, string $frCode, string $supplier): void
    {
        // Implement document creation logic
        DB::table('documents')->insert([
            'reference' => $newRefGen,
            'refer' => $refer,
            'fr_code' => $frCode,
            'supplier' => $supplier,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    private function addToTCreateDocumentBLAchat(string $newRefGen): void
    {
        // Implement the logic to add to T_CreateDocumentBLAchat table
        DB::table('t_create_document_bl_achat')->insert([
            'reference' => $newRefGen,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    private function insertToTArtScan(string $nPiece): void
    {
        // Implement the logic to insert to T_Art_Scan table
        DB::table('t_art_scan')->insert([
            'piece_number' => $nPiece,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    private function remDC(string $nPiece): void
    {
        // Implement the logic to remove DC
        DB::table('dc_table')
            ->where('piece_number', $nPiece)
            ->delete();
    }
}
