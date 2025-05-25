<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class ValidationController extends Controller
{




    private function updateStatut(string $statut, string $nPiece, string $refer): void
    {
        if ($statut === "Validé") {
            try {
                DB::beginTransaction();

                // Execute the update query
                $affectedRows = DB::table('your_table_name') // Replace with actual table name
                    ->where('piece_number', $nPiece) // Replace with actual column name
                    ->update([
                        // Add the columns you want to update
                        'statut' => $statut,
                        'updated_at' => now()
                    ]);

                if ($affectedRows > 0) {
                    $listeFournisseurs = $this->getFourniss($nPiece);

                    foreach ($listeFournisseurs as $lst) {
                        if (!empty($lst) && $lst !== "") {
                            $newRefGen = null;
                            $frCode = null;

                            switch ($lst) {
                                case "intercocina":
                                    $newRefGen = $this->genererNouvelleReference();
                                    $frCode = "FR001";
                                    break;
                                case "serie moble":
                                    $newRefGen = $this->genererNouvelleReference();
                                    $frCode = "FR002";
                                    break;
                                case "astidkora":
                                    $newRefGen = $this->genererNouvelleReference();
                                    $frCode = "FR003";
                                    break;
                            }

                            if ($newRefGen && $frCode) {
                                $this->createDoc($newRefGen, $refer, $frCode, $lst);
                                $this->addToTCreateDocumentBLAchat($newRefGen);
                            }
                        }
                    }

                    $this->insertToTArtScan($nPiece);
                    $this->remDC($nPiece);

                    // Commit the transaction
                    DB::commit();

                    // Instead of clearing DataGridView and reloading (desktop app specific),
                    // you might want to return success response or redirect
                    session()->flash('success', 'Statut mis à jour avec succès.');
                } else {
                    DB::rollBack();
                    session()->flash('error', 'Aucune mise à jour effectuée. Vérifiez le numéro de pièce.');
                }
            } catch (\Illuminate\Database\QueryException $ex) {
                DB::rollBack();
                Log::error('Erreur SQL lors de la mise à jour du statut: ' . $ex->getMessage());
                session()->flash('error', 'Erreur SQL : ' . $ex->getMessage());
                return;
            } catch (Exception $ex) {
                DB::rollBack();
                Log::error('Erreur lors de la mise à jour du statut: ' . $ex->getMessage());
                session()->flash('error', 'Erreur : ' . $ex->getMessage());
                return;
            }
        }
    }

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
