<?php

namespace App\Http\Controllers;

use App\Models\CurrentPiece;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Docentete;
use App\Models\Docligne;
use DateTime;
use Exception;

class DuplicationController extends Controller
{
    private const DO_TYPE = 0;
    private const CB_CREATION_USER = '69C8CD64-D06F-4097-9CAC-E488AC2610F9';



    private function generateHeure(): string
    {
        $now = new DateTime();
        $timeString = $now->format('His'); // HHmmss
        $timeString = str_pad($timeString, 9, '0', STR_PAD_LEFT);
        return $timeString;
    }





    public function duplicat($sourcePiece, $lines = [])
    {
        try {
            if (!empty($lines) && (is_array($lines) || $lines instanceof \Illuminate\Support\Collection)) {
                $doclignes = Docligne::with('line')->whereIn('cbMarq', $lines)->get();
            } else {
                $doclignes = Docligne::with('line')->where('DO_Piece', $sourcePiece)->get();
            }

            $DO_Piece = CurrentPiece::find('2')->DC_Piece;

            $duc = $this->createDocumentFromTemplate($sourcePiece, null,  $DO_Piece);




            foreach ($doclignes as $line) {

                $newDL_No = $this->createDocumentLineFromTemplate(
                    $line->DL_No,
                    $DO_Piece,
                    $duc->DO_Date,
                    $duc->DO_Tiers
                );

                // Insert into F_DOCLIGNEEMPL
                DB::table('F_DOCLIGNEEMPL')->insert([
                    'DL_No'             => $newDL_No,
                    'DP_No'             => 1,
                    'DL_Qte'            => $line->DL_Qte,
                    'DL_QteAControler'  => 0,
                    'cbCreationUser'    => self::CB_CREATION_USER,
                ]);
            }

            return response()->json(['success' => true]);
        } catch (Exception $e) {
            Log::error('duplicat operation failed: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function createDocumentFromTemplate(string $sourcePiece, $clinet, $DO_Piece)
    {
        try {
            $DO_Date = now()->format('Y-m-d H:i:s');

            // Get source row
            $source = (array) DB::connection('sqlsrv')
                ->table('F_DOCENTETE')
                ->where('DO_Piece', $sourcePiece)
                ->first();

            if (!$source) {
                Log::error("Source document not found: " . $sourcePiece);
                throw new Exception("Source document not found: " . $sourcePiece);
            }

            // Remove primary key and problematic columns
            unset($source['cbMarq']);
            unset($source['D.TIMBRE']);
            unset($source['cbDO_Piece']);
            unset($source['cbDO_Tiers']);
            unset($source['cbCT_NumPayeur']);
            unset($source['cbCA_Num']);
            unset($source['cbCG_Num']);
            unset($source['cbCT_NumCentrale']);
            unset($source['cbDO_FactureFrs']);
            unset($source['cbDO_PieceOrig']);


            // Override specific columns
            $source['DO_Piece']   = $DO_Piece;
            $source['DO_Date']    = $DO_Date;
            $source['DO_Heure']   = $this->generateHeure();
            $source['DO_Tiers']   = $clinet ?: $source['DO_Tiers'];

            // Replace all date columns with now()
            foreach ($source as $col => $val) {
                if (stripos($col, 'Date') !== false) {
                    $source[$col] = $DO_Date;
                }
            }

            // Insert and return new row
            $id = DB::connection('sqlsrv')
                ->table('F_DOCENTETE')
                ->insertGetId($source, 'cbMarq');


            $docentete = DB::connection('sqlsrv')
                ->table('F_DOCENTETE')
                ->where('cbMarq', $id)
                ->first();

            DB::table('F_DOCREGL')->insert([
                // "DR_No" => "",
                "DO_Domaine" => $docentete->DO_Domaine,
                "DO_Type" =>  $docentete->DO_Type,
                "DO_Piece" =>  $docentete->DO_Piece,
                "DR_TypeRegl" =>  2,
                "DR_Date" => now()->format('Y-m-d H:i:s'),
                "DR_Pourcent" => floatval(0),
                "DR_Montant" => floatval(0),
                "DR_MontantDev" => floatval(0),

                "DR_Equil" => 1,
                "EC_No" => 0,
                "DR_Regle" => 0,
                "N_Reglement" => 1,
                "CA_No" => 0,
                "DO_DocType" => $docentete->DO_DocType,
                "cbCreationUser"    => self::CB_CREATION_USER,


            ]);

            return $docentete;
        } catch (Exception $e) {
            Log::error('Docentete creation failed: ' . $e->getMessage());
            throw $e;
        }
    }


    public function createDocumentLineFromTemplate(int $DL_No, string $DO_Piece, string $DO_Date, string $CT_Num): int
    {
        try {
            // Get next DL_No
            $nextDL_No = DB::connection('sqlsrv')->selectOne(
                "SELECT ISNULL(MAX(DL_No), 0) + 1 AS NextDL_No FROM F_DOCLIGNE"
            )->NextDL_No;

            $sql = "
            INSERT INTO F_DOCLIGNE (
                DO_Domaine, DO_Type, CT_Num, DO_Piece,
                DL_PieceBC, DL_PieceBL,
                DO_Date, DL_DateBC, DL_DateBL, DL_Ligne, DO_Ref, DL_TNomencl,
                DL_TRemPied, DL_TRemExep, AR_Ref, DL_Design, DL_Qte,
                DL_QteBC, DL_QteBL, DL_PoidsNet, DL_PoidsBrut,
                DL_Remise01REM_Valeur, DL_Remise01REM_Type,
                DL_Remise02REM_Valeur, DL_Remise02REM_Type,
                DL_Remise03REM_Valeur, DL_Remise03REM_Type,
                DL_PrixUnitaire, DL_PUBC, DL_Taxe1, DL_TypeTaux1, DL_TypeTaxe1,
                DL_Taxe2, DL_TypeTaux2, DL_TypeTaxe2,
                CO_No, AG_No1, AG_No2,
                DL_PrixRU, DL_CMUP, DL_MvtStock, DT_No, AF_RefFourniss,
                EU_Enumere, EU_Qte, DL_TTC, DE_No, DL_TypePL,
                DL_PUDevise, DL_PUTTC, DL_No, DO_DateLivr, CA_Num,
                DL_Taxe3, DL_TypeTaux3, DL_TypeTaxe3, DL_Frais, DL_Valorise,
                AR_RefCompose, AC_RefClient,
                DL_MontantHT, DL_MontantTTC, DL_FactPoids, DL_Escompte,
                DL_PiecePL, DL_DatePL, DL_QtePL,
                RP_Code, DL_QteRessource, DL_DateAvancement,
                PF_Num, DL_CodeTaxe1, DL_CodeTaxe2, DL_CodeTaxe3,
                DL_PieceOFProd, DL_PieceDE, DL_DateDE, DL_QteDE, DL_Operation,
                CA_No, DO_DocType, cbProt,
                Nom, Hauteur, Largeur, Profondeur, Langeur,
                Couleur, Chant, Episseur, TRANSMIS, Poignée, Description, Rotation, DL_NoRef, DL_NonLivre,
                DL_NoColis, DL_NoLink
            )
            SELECT
                s.DO_Domaine,
                s.DO_Type,
                ?, -- new CT_Num
                ?, -- new DO_Piece
                s.DL_PieceBC,
                s.DL_PieceBL,
                ?, -- new DO_Date
                s.DL_DateBC,
                s.DL_DateBL,
                s.DL_Ligne,
                s.DO_Ref,
                s.DL_TNomencl,
                s.DL_TRemPied,
                s.DL_TRemExep,
                s.AR_Ref,
                s.DL_Design,
                s.DL_Qte,
                s.DL_QteBC,
                s.DL_QteBL,
                s.DL_PoidsNet,
                s.DL_PoidsBrut,
                s.DL_Remise01REM_Valeur,
                s.DL_Remise01REM_Type,
                s.DL_Remise02REM_Valeur,
                s.DL_Remise02REM_Type,
                s.DL_Remise03REM_Valeur,
                s.DL_Remise03REM_Type,
                s.DL_PrixUnitaire,
                s.DL_PUBC,
                s.DL_Taxe1,
                s.DL_TypeTaux1,
                s.DL_TypeTaxe1,
                s.DL_Taxe2,
                s.DL_TypeTaux2,
                s.DL_TypeTaxe2,
                s.CO_No,
                s.AG_No1,
                s.AG_No2,
                s.DL_PrixRU,
                s.DL_CMUP,
                s.DL_MvtStock,
                s.DT_No,
                s.AF_RefFourniss,
                s.EU_Enumere,
                s.EU_Qte,
                s.DL_TTC,
                s.DE_No,
                s.DL_TypePL,
                s.DL_PUDevise,
                s.DL_PUTTC,
                ?, -- new DL_No
                s.DO_DateLivr,
                s.CA_Num,
                s.DL_Taxe3,
                s.DL_TypeTaux3,
                s.DL_TypeTaxe3,
                s.DL_Frais,
                s.DL_Valorise,
                s.AR_RefCompose,
                s.AC_RefClient,
                s.DL_MontantHT,
                s.DL_MontantTTC,
                s.DL_FactPoids,
                s.DL_Escompte,
                s.DL_PiecePL,
                s.DL_DatePL,
                s.DL_QtePL,
                s.RP_Code,
                s.DL_QteRessource,
                s.DL_DateAvancement,
                s.PF_Num,
                s.DL_CodeTaxe1,
                s.DL_CodeTaxe2,
                s.DL_CodeTaxe3,
                s.DL_PieceOFProd,
                s.DL_PieceDE,
                s.DL_DateDE,
                s.DL_QteDE,
                s.DL_Operation,
                s.CA_No,
                s.DO_DocType,
                s.cbProt,
                s.Nom,
                s.Hauteur,
                s.Largeur,
                s.Profondeur,
                s.Langeur,
                s.Couleur,
                s.Chant,
                s.Episseur,
                s.TRANSMIS,
                s.Poignée,
                s.Description,
                s.Rotation,
                s.DL_NoRef,
                s.DL_NonLivre,
                s.DL_NoColis,
                s.DL_NoLink
            FROM F_DOCLIGNE s
            WHERE s.DL_No = ?
        ";

            DB::connection('sqlsrv')->insert($sql, [
                $CT_Num,
                $DO_Piece,
                $DO_Date,
                $nextDL_No,
                $DL_No
            ]);

            return $nextDL_No;
        } catch (Exception $e) {
            Log::error('Document line duplication failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
