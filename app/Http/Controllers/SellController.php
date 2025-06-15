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



    public function generatePiece()
    {
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


    private function dl_no_generate(): int
    {
        $result = 0;
        $maxNo = DB::table('F_DOCLIGNE')->max('DL_No');
        if (!is_null($maxNo)) {
            $result = (int)$maxNo + 1;
        } else {
            $result = 1;
        }
        return $result;
    }


    public function calculator($piece)
    {
        // Fetch document header
        $docentete = Docentete::where('DO_Piece', $piece)
            ->select('DO_Domaine', 'DO_Type', 'DO_Piece', 'DO_Ref', 'DO_Tiers', 'DO_TotalHTNet', 'DO_NetAPayer')
            ->first();

        // Fetch document lines
        $doclignes = Docligne::where('DO_Piece', $piece)
            ->whereHas("line", function ($line) {
                $line->where('company_id', auth()->user()->company_id);
            })
            ->select(
                'DO_Domaine',
                'DL_Ligne',
                'AR_Ref',
                'DO_Type',
                'CT_Num',
                'DO_Piece',
                'DO_Ref',
                'DL_Design',
                'DL_MontantHT',
                'DL_MontantTTC',
                'DL_PrixUnitaire',
                'DL_Taxe1',
                'DL_Qte'
            )
            ->get();

        // Calculate totals
        $totalTTC = $doclignes->sum('DL_MontantTTC');
        $totalHT = $doclignes->sum('DL_MontantHT');

        // Generate new piece
        $new_piece = $this->generatePiece();
        $ct_num = 'FR00' . auth()->user()->company_id;

        // Create document header
        $this->createDocumentFromTemplate(
            $new_piece,
            $piece,
            $ct_num,
            $this->generateHeure(),
            $totalHT,
            $totalTTC,
            $piece
        );



        // Create document lines
        $result = [];
        foreach ($doclignes as $line) {
            $do_date = now();
            $do_ref = $new_piece;
            $dl_no = $this->dl_no_generate();

            $this->createDocumentLineFromTemplate(
                $ct_num,
                $new_piece,
                $do_date,
                $do_ref,
                $ct_num,
                $dl_no,
                $line->AR_Ref,
                intval($line->DL_Ligne),
                $piece
            );


            DB::connection('sqlsrv')->unprepared("
                SET NOCOUNT ON;
                SET XACT_ABORT ON;
                DISABLE TRIGGER ALL ON F_DOCLIGNEEMPL;
            ");

            DB::table('F_DOCLIGNEEMPL')->insert([
                'DL_No'            => $dl_no,
                'DP_No'            => 1,
                'DL_Qte'           => $line->DL_Qte,
                'DL_QteAControler' => 0,
                'cbCreationUser'   => '69C8CD64-D06F-4097-9CAC-E488AC2610F9',
            ]);

            DB::connection('sqlsrv')->unprepared("
                SET NOCOUNT ON;
                SET XACT_ABORT ON;
                DISABLE TRIGGER ALL ON F_DOCLIGNEEMPL;
            ");


            // Incrémentation des stocks
            $qte = floatval($line->DL_Qte);




            try {
                DB::connection('sqlsrv')->unprepared("
                SET NOCOUNT ON;
                SET XACT_ABORT ON;
                DISABLE TRIGGER ALL ON F_ARTSTOCK;
            ");
                $updated = DB::table('F_ARTSTOCK')
                    ->where('AR_Ref', $line->AR_Ref)
                    ->update([
                        'AS_QteSto'  => DB::raw("AS_QteSto + {$qte}"),
                    ]);

                DB::connection('sqlsrv')->unprepared("
                SET NOCOUNT ON;
                SET XACT_ABORT ON;
                DISABLE TRIGGER ALL ON F_ARTSTOCK;
            ");
            } catch (\Throwable $th) {
                DB::connection('sqlsrv')->unprepared("
                SET NOCOUNT ON;
                SET XACT_ABORT ON;
                DISABLE TRIGGER ALL ON F_ARTSTOCK;
            ");
            }




            logger("No rows updated for AR_Ref: {$line->AR_Ref} with qte: {$qte}");


            $result[] = [
                "ct_num" => $ct_num,
                'new_piece' => $new_piece,
                'd_date' => $do_date,
                'do_ref' => $do_ref,
                'ref_fournisseur' => $ct_num,
                'dl_no' => $dl_no,
                'AR_Ref' => $line->AR_Ref,
                'DL_Ligne' => intval($line->DL_Ligne),
            ];
        }

        try {
            DB::beginTransaction();

            // Disable triggers before the insert
            DB::connection('sqlsrv')->unprepared("
                SET NOCOUNT ON;
                SET XACT_ABORT ON;
                DISABLE TRIGGER ALL ON F_DOCENTETE;
            ");

            // Insert the document


            // Commit the transaction
            DB::commit();
        } catch (\Exception $e) {
            // Rollback the transaction if any error occurs
            DB::rollBack();

            // Re-throw the exception after rollback
            throw $e;
        } finally {
            // Ensure triggers are always re-enabled

        }

        return response()->json([
            'result' => $result,
            'docentete' => $docentete,
            'totalHT' => $totalHT,
            'totalTTC' => $totalTTC,
            'houre' => $this->generateHeure(),
            'ref' => $docentete->DO_Piece,
            'last_document' => $new_piece
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
                // ->where('DO_Type', 3)
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
                $doPiece,
                $doRef,
                $doTiers,
                $doTiers,
                $doTiers,
                $doHeure,
                $mtEntrer,
                $mtEntrerTTC,
                $mtEntrerTTC,
                $mtEntrerTTC,
                $sourcePiece
            ]);

            return $result;
        } catch (\Exception $e) {
            \Log::error('Error executing raw document creation query: ' . $e->getMessage());
            return false;
        }
    }





    // Docling

    public function createDocumentLineFromTemplate(
        string $ctNum,
        string $doPiece,
        string $doDate,
        string $doRef,
        string $afRefFourniss,
        int $DO_NO,
        string $refArticle,
        int $ligne,
        string $piece
    ) {
        // Define constants for clarity
        $DO_DOMAINE_SALES = 1;
        $DO_TYPE_CUSTOM = 13;
        $TAXE_STANDARD = 20;

        try {
            // Retrieve source document line
            $sourceDocLine = Docligne::where([
                ['DO_Domaine', '=', 0],
                ['DO_Type', '=', 2],
                ['DO_Piece', '=', $piece],
                ['AR_Ref', '=', $refArticle],
                ['DL_Ligne', '=', $ligne],
            ])->first();

            if (!$sourceDocLine) {
                throw new \Exception("Source document line not found.");
            }

            // Calculate prices with discount and margin
            $basePrice = (float) $sourceDocLine->DL_PrixUnitaire;
            $discountRate = (float) ($sourceDocLine->DL_Remise01REM_Valeur ?? 0) / 100;
            $priceAfterDiscount = $basePrice * (1 - $discountRate);
            $calculatedPrice = round($priceAfterDiscount * 0.92, 2);
            $calculatedPriceTTC = round($calculatedPrice * 1.2, 2);
            $quantity = (float) $sourceDocLine->DL_QteBL;

            return DB::transaction(function () use (
                $ctNum,
                $doPiece,
                $doDate,
                $doRef,
                $afRefFourniss,
                $DO_NO,
                $sourceDocLine,
                $calculatedPrice,
                $calculatedPriceTTC,
                $DO_DOMAINE_SALES,
                $DO_TYPE_CUSTOM,
                $TAXE_STANDARD,
                $quantity
            ) {
                $insertData = [
                    'DO_Domaine' => $DO_DOMAINE_SALES,
                    'DO_Type' => $DO_TYPE_CUSTOM,
                    'CT_Num' => $ctNum,
                    'DO_Piece' => $doPiece,
                    'DL_PieceBC' => '',
                    'DL_PieceBL' => '',
                    'DO_Date' => $doDate,
                    'DL_DateBC' => $doDate,
                    'DL_DateBL' => $doDate,
                    'DL_Ligne' => $sourceDocLine->DL_Ligne,
                    'DO_Ref' => $doRef,
                    'DL_TNomencl' => 0,
                    'DL_TRemPied' => 0,
                    'DL_TRemExep' => 0,
                    'AR_Ref' => $sourceDocLine->AR_Ref,
                    'DL_Design' => $sourceDocLine->DL_Design,
                    'DL_Qte' => $quantity,
                    'DL_QteBC' => $quantity,
                    'DL_QteBL' => $quantity,
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
                    'DL_QteDE' => $quantity,
                    'EU_Qte' => $quantity,
                    'EU_Enumere' => $sourceDocLine->EU_Enumere ?? '',
                    'DL_Taxe1' => $TAXE_STANDARD,
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
                    'DL_MontantHT' => round($quantity * $calculatedPrice, 2),
                    'DL_MontantTTC' => round($quantity * $calculatedPriceTTC, 2),
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
                    'DL_DateDE' => $doDate,
                    'DL_Operation' => '',
                    'DL_NoSousTotal' => 0,
                    'CA_No' => 0,
                    'DO_DocType' => $DO_TYPE_CUSTOM,
                    'DL_CodeTaxe1' => 'D20',
                    'cbCreationUser' => '69C8CD64-D06F-4097-9CAC-E488AC2610F9',
                ];

                DB::connection('sqlsrv')->unprepared("
                    SET NOCOUNT ON;
                    SET XACT_ABORT ON;
                    DISABLE TRIGGER ALL ON F_DOCLIGNE;
                ");

                $newDocLine = Docligne::create($insertData);

                DB::connection('sqlsrv')->unprepared("
                    ENABLE TRIGGER ALL ON F_DOCLIGNE;
                ");

                return $newDocLine->id ?? true;
            });
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
}
