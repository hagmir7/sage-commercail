<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use App\Models\Docentete;
use App\Models\Docligne;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class SellController extends Controller
{

    function generateHeure()
    {
        $now = now();
        $formatted = $now->format('His');
        return str_pad($formatted, 9, '0', STR_PAD_LEFT);
    }



    public function generatePiece(){

        $last_piece = Docentete::select('DO_Piece')
            ->where('DO_Type', '13')
            ->where('DO_Piece', 'LIKE', '%BLX%')
            ->orderByDesc('DO_Piece')
            ->first()
            ?->DO_Piece ?? '25BLX000000';


        preg_match('/^([A-Z0-9]+)(\d{6})$/i', $last_piece, $matches);

        if (count($matches) === 3) {
            $prefix = $matches[1];
            $number = (int)$matches[2];
            $new_number = str_pad($number + 1, 6, '0', STR_PAD_LEFT);
            $new_piece = $prefix . $new_number;
        } else {
            $new_piece = '25BLX000001';
        }

        return $new_piece;
    }


    public function calculator($piece){

        $docentete = Docentete::where('DO_Piece', $piece)
            ->select('DO_Domaine', 'DO_Type', 'DO_Piece', 'DO_Ref', 'DO_Tiers', 'DO_TotalHTNet', 'DO_NetAPayer')
            ->first();


        $doclignes = Docligne::where('DO_Piece', $piece)
            ->whereHas("line", function($line){
                $line->where('company_id', 1);
            })
            ->select('DO_Domaine', 'DO_Type', 'CT_Num', 'DO_Piece', 'DO_Ref', 'DL_Design', 'DL_MontantHT', 'DL_MontantTTC', 'DL_PrixUnitaire', 'DL_Taxe1')
            ->get();

        $totalTTC = $doclignes->sum('DL_MontantTTC');
        $totalHT = $doclignes->sum('DL_MontantHT');



        // $result = $this->createDocumentFromTemplate(
        //     $this->generatePiece(),
        //     "REF Test",
        //     'FR001',
        //     $this->generateHeure(),
        //     $totalHT,
        //     $totalTTC,
        //     $piece
        // );

        // return response()->json($result);


        return response()->json([
            'docentete' => $docentete,
            'totalHT' => $totalHT,
            'totalTTC' => $totalTTC,
            'houre' => $this->generateHeure(),
            'ref' => $docentete->DO_Piece,
            'last_document' => $this->generatePiece(),
            'doclignes' => $doclignes,
        ]);
    }



    public function createDocumentFromTemplate(
        string $doPiece, // ✅
        string $doRef, // ✅
        string $doTiers, // ✅
        string $doHeure, // ✅
        float $mtEntrer, // ✅
        float $mtEntrerTTC, // ✅
        string $sourcePiece
    ) {
        try {
            // Get source document data
            $sourceDocument = Docentete::where('DO_Domaine', 0)
                ->where('DO_Type', 3)
                ->where('DO_Piece', $sourcePiece)
                ->first();

            if (!$sourceDocument) {
                throw new \Exception("Source document not found");
            }

            // Prepare data for insertion
            $insertData = [
                'DO_DocType' => 13,
                'DO_Devise' => 1,
                'DO_Domaine' => 1,
                'DO_Type' => 13,
                'DO_Statut' => 0,
                'DO_Piece' => $doPiece,
                'DO_Date' => Carbon::now()->format('Y-m-d H:i:s'),
                'DO_Ref' => $doRef,
                'DO_Tiers' => $doTiers,
                'CO_No' => 0,
                'DO_Period' => 1,
                'DO_Cours' => 1,
                'DE_No' => 1,
                'cbDE_No' => 1,
                'LI_No' => 0,
                'CT_NumPayeur' => $doTiers,
                'DO_Expedit' => 1,
                'DO_NbFacture' => 1,
                'DO_BLFact' => 0,
                'DO_TxEscompte' => 0,
                'DO_Reliquat' => 0,
                'DO_Imprim' => 0,
                'CA_Num' => '',
                'DO_Coord01' => '',
                'DO_Coord02' => '',
                'DO_Coord03' => '',
                'DO_Coord04' => '',
                'DO_Souche' => ($doTiers === 'FR001') ? $sourceDocument->DO_Souche : 0,
                'DO_DateLivr' => $sourceDocument->DO_DateLivr,
                'DO_Condition' => 1,
                'DO_Tarif' => 1,
                'DO_Colisage' => 1,
                'DO_TypeColis' => 1,
                'DO_Transaction' => 11,
                'DO_Langue' => 0,
                'DO_Ecart' => 0,
                'DO_Regime' => 11,
                'N_CatCompta' => 5,
                'DO_Ventile' => 0,
                'AB_No' => 0,
                'DO_DebutAbo' => $sourceDocument->DO_DebutAbo,
                'DO_FinAbo' => $sourceDocument->DO_FinAbo,
                'DO_DebutPeriod' => $sourceDocument->DO_DebutPeriod,
                'DO_FinPeriod' => $sourceDocument->DO_FinPeriod,
                'CG_Num' => 44110000,
                'DO_Heure' => $doHeure,
                'CA_No' => 0,
                'CO_NoCaissier' => 0,
                'DO_Transfere' => 0,
                'DO_Cloture' => 0,
                'DO_NoWeb' => '',
                'DO_Attente' => 0,
                'DO_Provenance' => 0,
                'CA_NumIFRS' => '',
                'MR_No' => 0,
                'DO_TypeFrais' => 0,
                'DO_ValFrais' => 0,
                'DO_TypeLigneFrais' => 0,
                'DO_TypeFranco' => 0,
                'DO_ValFranco' => 0,
                'DO_TypeLigneFranco' => 0,
                'DO_Taxe1' => 0,
                'DO_TypeTaux1' => 0,
                'DO_TypeTaxe1' => 0,
                'DO_Taxe2' => 0,
                'DO_TypeTaxe2' => 0,
                'DO_Taxe3' => 0,
                'DO_TypeTaux3' => 0,
                'DO_TypeTaxe3' => 0,
                'DO_MajCpta' => 0,
                'DO_Motif' => '',
                'DO_Contact' => '',
                'DO_FactureElec' => 0,
                'DO_TypeTransac' => 0,
                'DO_DateLivrRealisee' => $sourceDocument->DO_DateLivrRealisee,
                'DO_DateExpedition' => $sourceDocument->DO_DateExpedition,
                'DO_FactureFrs' => '',
                'DO_PieceOrig' => '',
                'DO_EStatut' => 0,
                'DO_DemandeRegul' => 0,
                'ET_No' => 0,
                'DO_Valide' => 0,
                'DO_Coffre' => 0,
                'DO_StatutBAP' => 0,
                'DO_Escompte' => 0,
                'DO_TypeCalcul' => 0,
                'DO_MontantRegle' => 0,
                'DO_AdressePaiement' => '',
                'DO_PaiementLigne' => 0,
                'DO_MotifDevis' => 0,
                'DO_Conversion' => 0,
                'DO_TypeTaux2' => 0,
                'DO_TotalHTNet' => $mtEntrer,
                'DO_TotalTTC' => $mtEntrerTTC,
                'DO_NetAPayer' => $mtEntrerTTC,
                'Montant_S_DT' => $mtEntrerTTC,
                'cbCreationUser' => '69C8CD64-D06F-4097-9CAC-E488AC2610F9'
            ];

            try {
                DB::beginTransaction();

                // Disable triggers before the insert
                DB::connection('sqlsrv')->unprepared("
                    SET NOCOUNT ON;
                    SET XACT_ABORT ON;
                    DISABLE TRIGGER ALL ON F_DOCENTETE;
                ");

                // Insert the document
                $newDocument = Docentete::create($insertData);

                // Commit the transaction
                DB::commit();
            } catch (\Exception $e) {
                // Rollback the transaction if any error occurs
                DB::rollBack();

                // Re-throw the exception after rollback
                throw $e;
            } finally {
                // Ensure triggers are always re-enabled
                DB::connection('sqlsrv')->unprepared("
                    ENABLE TRIGGER ALL ON F_DOCENTETE;
                ");
            }

            return $newDocument->id ?? true;
        } catch (\Exception $e) {
            \Log::error('Error creating document from template: ' . $e->getMessage());
            return false;
        }
    }



    public function createDocumentFromTemplateRaw(
        string $doPiece,
        string $doRef,
        string $doTiers,
        string $doHeure,
        float $mtEntrer,
        float $mtEntrerTTC,
        string $sourcePiece
    ): bool {
        try {
            $query = "
                INSERT INTO F_DOCENTETE (DO_DocType, DO_Devise, DO_Domaine, DO_Type, DO_Statut, DO_Piece, DO_Date,
                DO_Ref, DO_Tiers, CO_No, DO_Period, DO_Cours, DE_No, cbDE_No, LI_No, CT_NumPayeur, DO_Expedit, DO_NbFacture, DO_BLFact,
                DO_TxEscompte, DO_Reliquat, DO_Imprim, CA_Num, DO_Coord01, DO_Coord02, DO_Coord03, DO_Coord04, DO_Souche, DO_DateLivr,
                DO_Condition, DO_Tarif, DO_Colisage, DO_TypeColis, DO_Transaction, DO_Langue, DO_Ecart, DO_Regime, N_CatCompta,
                DO_Ventile, AB_No, DO_DebutAbo, DO_FinAbo, DO_DebutPeriod, DO_FinPeriod, CG_Num, DO_Heure, CA_No, CO_NoCaissier,
                DO_Transfere,DO_Cloture, DO_NoWeb, DO_Attente, DO_Provenance, CA_NumIFRS, MR_No, DO_TypeFrais, DO_ValFrais, DO_TypeLigneFrais,
                DO_TypeFranco, DO_ValFranco, DO_TypeLigneFranco, DO_Taxe1, DO_TypeTaux1, DO_TypeTaxe1, DO_Taxe2, DO_TypeTaxe2, DO_Taxe3,
                DO_TypeTaux3, DO_TypeTaxe3, DO_MajCpta, DO_Motif, DO_Contact, DO_FactureElec, DO_TypeTransac, DO_DateLivrRealisee,
                DO_DateExpedition, DO_FactureFrs, DO_PieceOrig, DO_EStatut, DO_DemandeRegul, ET_No, DO_Valide, DO_Coffre, DO_StatutBAP,
                DO_Escompte, DO_TypeCalcul, DO_MontantRegle, DO_AdressePaiement, DO_PaiementLigne, DO_MotifDevis, DO_Conversion, DO_TypeTaux2,
                DO_TotalHTNet, DO_TotalTTC, DO_NetAPayer, Montant_S_DT, cbCreationUser)
                SELECT  13, 1, 1, 13, 0, ?, NOW(), ?, ?, 0, 1, 1, 1, 1,
                0, ?, 1, 1, 0, 0, 0, 0, '', '', '', '', '', CASE WHEN ? = 'FR001' THEN DO_Souche ELSE 0 END, DO_DateLivr,
                1, 1, 1, 1, 11, 0, 0, 11, 5, 0,
                0, DO_DebutAbo, DO_FinAbo, DO_DebutPeriod, DO_FinPeriod, 44110000, ?, 0, 0, 0,
                0, '', 0, 0, '', 0, 0, 0, 0, 0, 0,
                0, 0, 0, 0, 0, 0, 0, 0, 0,
                0, '', '', 0, 0, DO_DateLivrRealisee, DO_DateExpedition, '', '', 0, 0,
                0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, ?, ?, ?, ?, '69C8CD64-D06F-4097-9CAC-E488AC2610F9'
                FROM F_DOCENTETE
                WHERE DO_Domaine = 0 AND DO_Type = 2 AND DO_Piece = ?
            ";

            $result = DB::insert($query, [
                $doPiece, $doRef ,$doTiers ,$doTiers ,$doTiers ,$doHeure ,$mtEntrer ,$mtEntrerTTC ,$mtEntrerTTC ,$mtEntrerTTC ,$sourcePiece
            ]);

            return $result;
        } catch (\Exception $e) {
            \Log::error('Error executing raw document creation query: ' . $e->getMessage());
            return false;
        }
    }


    public function storeDocentete(Request $request)
    {

        $result = $this->createDocumentFromTemplate(
            $request->do_piece,
            $request->do_ref,
            $request->do_tiers,
            $request->do_heure,
            $request->mt_entrer,
            $request->mt_entrer_ttc,
            $request->source_piece
        );

        if ($result) {
            return response()->json([
                'success' => true,
                'message' => 'Document created successfully',
                'id' => $result
            ], 201);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to create document'
        ], 500);
    }



    // Docling

    public function createDocumentLineFromTemplate(
        string $ctNum,
        string $doDate,
        string $maVariable,
        string $doRef,
        string $afRefFourniss,
        int $DO_NO,
        string $doPiece,
        string $refArticle,
        int $ligne
    ) {
        try {
            // Get source document line data
            $sourceDocLine = Docligne::where('DO_Domaine', 0)
                ->where('DO_Type', 2)
                ->where('DO_Piece', $doPiece)
                ->where('AR_Ref', $refArticle)
                ->where('DL_Ligne', $ligne)
                ->first();

            if (!$sourceDocLine) {
                throw new \Exception("Source document line not found");
            }

            // Calculate prices with discount and margin
            $basePrice = $sourceDocLine->DL_PrixUnitaire;
            $discount = $sourceDocLine->DL_Remise01REM_Valeur / 100;
            $calculatedPrice = round($basePrice * (1 - round($discount, 2)) * 0.92, 2);
            $calculatedPriceTTC = round($calculatedPrice * 1.2, 2);

            // Prepare data for insertion
            $insertData = [
                'DO_Domaine' => 1,
                'DO_Type' => 13,
                'CT_Num' => $ctNum,
                'DO_Piece' => $doDate,
                'DL_PieceBC' => '',
                'DL_PieceBL' => '',
                'DO_Date' => $maVariable,
                'DL_DateBC' => $maVariable,
                'DL_DateBL' => $maVariable,
                'DL_Ligne' => $sourceDocLine->DL_Ligne,
                'DO_Ref' => $doRef,
                'DL_TNomencl' => 0,
                'DL_TRemPied' => 0,
                'DL_TRemExep' => 0,
                'AR_Ref' => $sourceDocLine->AR_Ref,
                'DL_Design' => $sourceDocLine->DL_Design,
                'DL_Qte' => $sourceDocLine->DL_QteBL,
                'DL_QteBC' => $sourceDocLine->DL_QteBL,
                'DL_QteBL' => $sourceDocLine->DL_QteBL,
                'DL_PoidsNet' => 0,
                'DL_PoidsBrut' => 0,
                'DL_Remise01REM_Valeur' => 0,
                'DL_Remise01REM_Type' => 0,
                'DL_Remise02REM_Valeur' => 0,
                'DL_Remise02REM_Type' => 0,
                'DL_Remise03REM_Valeur' => 0,
                'DL_Remise03REM_Type' => 0,
                'DL_PrixUnitaire' => $calculatedPrice,
                'DL_PUBC' => 0,
                'DL_QteDE' => $sourceDocLine->DL_QteBL,
                'EU_Qte' => $sourceDocLine->DL_QteBL,
                'EU_Enumere' => $sourceDocLine->EU_Enumere,
                'DL_Taxe1' => 20,
                'DL_TypeTaux1' => 0,
                'DL_Taxe2' => 0,
                'DL_TypeTaux2' => 0,
                'CO_No' => 0,
                'AG_No1' => 0,
                'AG_No2' => 0,
                'DL_PrixRU' => 0,
                'DL_CMUP' => 0,
                'DL_MvtStock' => 1,
                'DT_No' => 0,
                'AF_RefFourniss' => $afRefFourniss,
                'DL_TTC' => 0,
                'DE_No' => 1,
                'DL_NoRef' => 1,
                'DL_TypePL' => 0,
                'DL_PUDevise' => $calculatedPrice,
                'DL_PUTTC' => $calculatedPriceTTC,
                'DO_DateLivr' => '1753-01-01 00:00:00',
                'CA_Num' => '',
                'DL_Taxe3' => 0,
                'DL_TypeTaux3' => 0,
                'DL_TypeTaxe3' => 0,
                'DL_Frais' => 0,
                'DL_Valorise' => 1,
                'DL_NonLivre' => 0,
                'AC_RefClient' => '',
                'DL_MontantHT' => $sourceDocLine->DL_QteBL * $calculatedPrice,
                'DL_MontantTTC' => $sourceDocLine->DL_QteBL * $calculatedPriceTTC,
                'DL_FactPoids' => 0,
                'DL_No' => $DO_NO,
                'DL_TypeTaxe1' => 0,
                'DL_TypeTaxe2' => 0,
                'DL_Escompte' => 0,
                'DL_PiecePL' => '',
                'DL_DatePL' => '1753-01-01 00:00:00',
                'DL_QtePL' => 0,
                'DL_NoColis' => '',
                'DL_NoLink' => 0,
                'DL_QteRessource' => 0,
                'DL_DateAvancement' => '1753-01-01 00:00:00',
                'PF_Num' => '',
                'DL_PieceOFProd' => 0,
                'DL_PieceDE' => '',
                'DL_DateDE' => $maVariable,
                'DL_Operation' => '',
                'DL_NoSousTotal' => 0,
                'CA_No' => 0,
                'DO_DocType' => 13,
                'DL_CodeTaxe1' => 'D20',
                'cbCreationUser' => '69C8CD64-D06F-4097-9CAC-E488AC2610F9'
            ];

            // Create new document line record
            $newDocLine = Docligne::create($insertData);

            return $newDocLine->id ?? true;

        } catch (\Exception $e) {
            \Log::error('Error creating document line from template: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Alternative method using Query Builder for direct SQL execution
     *
     * @param string $ctNum CT number
     * @param string $doDate Document date
     * @param string $maVariable Variable date/reference
     * @param string $doRef Document reference
     * @param string $afRefFourniss Supplier reference
     * @param int $DO_NO Document line number
     * @param string $doPiece Source document piece
     * @param string $refArticle Article reference
     * @param int $ligne Document line number (source)
     * @return bool
     */
    public function createDocumentLineFromTemplateRaw(
        string $ctNum,
        string $doDate,
        string $maVariable,
        string $doRef,
        string $afRefFourniss,
        int $DO_NO,
        string $doPiece,
        string $refArticle,
        int $ligne
    ): bool {
        try {
            $query = "
                INSERT INTO F_DOCLIGNE (DO_Domaine, DO_Type, CT_Num, DO_Piece, DL_PieceBC, DL_PieceBL, DO_Date, DL_DateBC, DL_DateBL, DL_Ligne,
                DO_Ref, DL_TNomencl, DL_TRemPied, DL_TRemExep, AR_Ref, DL_Design, DL_Qte, DL_QteBC, DL_QteBL, DL_PoidsNet, DL_PoidsBrut,
                DL_Remise01REM_Valeur, DL_Remise01REM_Type, DL_Remise02REM_Valeur, DL_Remise02REM_Type, DL_Remise03REM_Valeur, DL_Remise03REM_Type,
                DL_PrixUnitaire, DL_PUBC, DL_QteDE, EU_Qte, EU_Enumere, DL_Taxe1, DL_TypeTaux1, DL_Taxe2, DL_TypeTaux2,
                CO_No, AG_No1, AG_No2, DL_PrixRU, DL_CMUP, DL_MvtStock, DT_No, AF_RefFourniss, DL_TTC, DE_No, DL_NoRef,
                DL_TypePL, DL_PUDevise, DL_PUTTC, DO_DateLivr, CA_Num, DL_Taxe3, DL_TypeTaux3, DL_TypeTaxe3, DL_Frais, DL_Valorise,
                DL_NonLivre, AC_RefClient, DL_MontantHT, DL_MontantTTC, DL_FactPoids, DL_No, DL_TypeTaxe1, DL_TypeTaxe2, DL_Escompte, DL_PiecePL, DL_DatePL, DL_QtePL, DL_NoColis, DL_NoLink,
                DL_QteRessource, DL_DateAvancement, PF_Num, DL_PieceOFProd, DL_PieceDE, DL_DateDE, DL_Operation, DL_NoSousTotal, CA_No, DO_DocType, DL_CodeTaxe1, cbCreationUser)
                SELECT 1, 13, ?, ?, '', '', ?, ?, ?, DL_Ligne, ?, 0,
                0, 0, AR_Ref, DL_Design, DL_QteBL, DL_QteBL, DL_QteBL, 0, 0, 0,
                0, 0, 0, 0, 0, ROUND((DL_PrixUnitaire * (1 - ROUND((DL_Remise01REM_Valeur / 100),2)) * 0.92), 2) , 0, DL_QteBL ,
                DL_QteBL, EU_Enumere, 20, 0, 0, 0, 0, 0, 0, 0, 0, 1, 0, ?,
                0, 1, 1, 0, ROUND((DL_PrixUnitaire * (1 - ROUND((DL_Remise01REM_Valeur / 100),2)) * 0.92), 2), ROUND(ROUND((DL_PrixUnitaire * (1 - ROUND((DL_Remise01REM_Valeur / 100),2)) * 0.92), 2) * 1.2, 2), '1753-01-01 00:00:00', '', 0, 0, 0, 0, 1,
                0, '', DL_QteBL * ROUND((DL_PrixUnitaire * (1 - ROUND((DL_Remise01REM_Valeur / 100),2)) * 0.92), 2), DL_QteBL * ROUND(ROUND((DL_PrixUnitaire * (1 - ROUND((DL_Remise01REM_Valeur / 100),2)) * 0.92), 2) * 1.2, 2), 0, ?, 0, 0, 0, '', '1753-01-01 00:00:00', 0, '', 0, 0, '1753-01-01 00:00:00', '', 0,
                '', ?, '', 0, 0, 13, 'D20', '69C8CD64-D06F-4097-9CAC-E488AC2610F9'
                FROM F_DOCLIGNE WHERE DO_Domaine=0 AND DO_Type=2 AND DO_Piece=? AND AR_Ref=? AND DL_Ligne=?
            ";

            $result = DB::insert($query, [
                $ctNum,
                $doDate,
                $maVariable,
                $maVariable,
                $maVariable,
                $doRef,
                $afRefFourniss,
                $DO_NO,
                $maVariable,
                $doPiece,
                $refArticle,
                $ligne
            ]);

            return $result;

        } catch (\Exception $e) {
            \Log::error('Error executing raw document line creation query: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Batch create multiple document lines from template
     *
     * @param array $lines Array of line parameters
     * @return array Results array with success/failure for each line
     */
    public function createMultipleDocumentLines(array $lines): array
    {
        $results = [];

        foreach ($lines as $index => $line) {
            try {
                $result = $this->createDocumentLineFromTemplate(
                    $line['ct_num'],
                    $line['do_date'],
                    $line['ma_variable'],
                    $line['do_ref'],
                    $line['af_ref_fourniss'],
                    $line['dl_no'],
                    $line['do_piece'],
                    $line['ref_article'],
                    $line['ligne']
                );

                $results[$index] = [
                    'success' => (bool)$result,
                    'id' => $result,
                    'line_data' => $line
                ];

            } catch (\Exception $e) {
                $results[$index] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'line_data' => $line
                ];
            }
        }

        return $results;
    }



    public function storeDoclign(Request $request)
    {

        $result = $this->createDocumentLineFromTemplate(
            $request->ct_num,
            $request->do_date,
            $request->ma_variable,
            $request->do_ref,
            $request->af_ref_fourniss,
            $request->dl_no,
            $request->do_piece,
            $request->ref_article,
            $request->ligne
        );

        if ($result) {
            return response()->json([
                'success' => true,
                'message' => 'Document line created successfully',
                'id' => $result
            ], 201);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to create document line'
        ], 500);
    }

    /**
     * Controller method to hanligne multiple lines HTTP request
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeBatch(Request $request)
    {
        $request->validate([
            'lines' => 'required|array|min:1',
            'lines.*.ct_num' => 'required|string',
            'lines.*.do_date' => 'required|string',
            'lines.*.ma_variable' => 'required|string',
            'lines.*.do_ref' => 'required|string',
            'lines.*.af_ref_fourniss' => 'required|string',
            'lines.*.dl_no' => 'required|integer',
            'lines.*.do_piece' => 'required|string',
            'lines.*.ref_article' => 'required|string',
            'lines.*.ligne' => 'required|integer'
        ]);

        $results = $this->createMultipleDocumentLines($request->lines);

        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $totalCount = count($results);

        return response()->json([
            'success' => $successCount > 0,
            'message' => "Successfully created {$successCount}/{$totalCount} document lines",
            'results' => $results,
            'summary' => [
                'total' => $totalCount,
                'success' => $successCount,
                'failed' => $totalCount - $successCount
            ]
        ], $successCount > 0 ? 201 : 500);
    }

}
