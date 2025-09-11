<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\Docentete;
use App\Models\Docligne;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Exception;

class SellController extends Controller
{
    private const DO_TYPE = 13;
    private const CB_CREATION_USER = '69C8CD64-D06F-4097-9CAC-E488AC2610F9';

    private function generateHeure(): string
    {
        return str_pad(now()->format('His'), 9, '0', STR_PAD_LEFT);
    }

    private function generatePiece(): string
    {
        $lastPiece = Docentete::where('DO_Type', self::DO_TYPE)
            ->where('DO_Piece', 'LIKE', '%BLX%')
            ->orderByDesc('DO_Piece')
            ->value('DO_Piece') ?? '25BLX000000';

        preg_match('/^([A-Z0-9]+)(\d{6})$/i', $lastPiece, $matches);

        if (count($matches) === 3) {
            return $matches[1] . str_pad((int)$matches[2] + 1, 6, '0', STR_PAD_LEFT);
        }

        return '25BLX000001';
    }

    public function calculator($sourcePiece, $lines = [])
    {
        try {
            if (!empty($lines) && (is_array($lines) || $lines instanceof \Illuminate\Support\Collection)) {
                $doclignes = Docligne::whereIn('cbMarq', $lines)->get();
            } else {
                $doclignes = Docligne::where('DO_Piece', $sourcePiece)->get();
            }

            $DO_Piece = $this->generatePiece();

            // Create document header
            $DO_Date = $this->createDocumentFromTemplate($sourcePiece, $DO_Piece);

            foreach ($doclignes as $line) {
                // Create line and get the new DL_No
                $newDL_No = $this->createDocumentLineFromTemplate($line->DL_No, $DO_Piece, $DO_Date, intval($line->DL_QteBC));

                // Insert into F_DOCLIGNEEMPL
                DB::table('F_DOCLIGNEEMPL')->insert([
                    'DL_No'             => $newDL_No,
                    'DP_No'             => 1,
                    'DL_Qte'            => $line->DL_Qte,
                    'DL_QteAControler'  => 0,
                    'cbCreationUser'    => self::CB_CREATION_USER,
                ]);

                // Update stock
                DB::table('F_ARTSTOCK')
                    ->where('AR_Ref', $line->AR_Ref)
                    ->update([
                        'AS_QteSto' => DB::raw("AS_QteSto + " . floatval($line->DL_Qte))
                    ]);
            }

            // DB::commit();
            return response()->json(['success' => true]);
        } catch (Exception $e) {
            // DB::rollback();
            Log::error('Calculator operation failed: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function createDocumentFromTemplate(string $sourcePiece, string $DO_Piece): string
    {
        try {
            $DO_Domaine = 1;
            $DO_Type    = 13;
            $DO_Date    = now()->format('Y-m-d H:i:s');
            $DO_Statut  = 0;

            // Step 1: Get the source row
            $source = DB::connection('sqlsrv')
                ->table('F_DOCENTETE')
                ->where('DO_Piece', $sourcePiece)
                ->first();

            if (!$source) {
                Log::error("Source document not found: " . $sourcePiece);
                throw new Exception("Source document not found: " . $sourcePiece);
            }

            DB::connection('sqlsrv')->table('F_DOCENTETE')->insert([
                'DO_Domaine'       => $DO_Domaine,
                'DO_Type'          => $DO_Type,
                'DO_Piece'         => $DO_Piece,
                'DO_Date'          => $DO_Date,
                'DO_Ref'           => $source->DO_Ref,
                'DO_Tiers'         => "FR001",
                'CO_No'            => $source->CO_No,
                'cbCreationUser'   => '00000000-0000-0000-0000-000000000000',
                'cbModification'   => DB::raw('GETDATE()'),
                'cbCreation'       => DB::raw('GETDATE()'),
                'DO_Statut'        => $DO_Statut,
            ]);

            return $DO_Date;
        } catch (Exception $e) {
            Log::error('Docentete creation failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function createDocumentLineFromTemplate(int $DL_No, $DO_Piece, $DO_Date, $DL_QteBL): int
    {
        try {
            $CT_Num = "FR001";

            // First, get the next DL_No
            $nextDL_No = DB::connection('sqlsrv')->select("SELECT ISNULL(MAX(DL_No), 0) + 1 AS NextDL_No FROM F_DOCLIGNE")[0]->NextDL_No;

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
                EU_Enumere, EU_Qte, DL_TTC, DE_No,  DL_TypePL,
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
                Couleur, Chant, Episseur, TRANSMIS, Poignée, Description, Rotation
            )
            SELECT
                1,                                   -- DO_Domaine
                13,                                  -- DO_Type
                ?,                                   -- CT_Num
                ?,                                   -- DO_Piece
                LEFT(s.DL_PieceBC, 13),
                LEFT(s.DL_PieceBL, 13),
                ?,                                   -- DO_Date
                s.DL_DateBC,
                s.DL_DateBL,
                s.DL_Ligne,
                LEFT(s.DO_Ref, 17),
                s.DL_TNomencl,
                s.DL_TRemPied,
                s.DL_TRemExep,
                LEFT(s.AR_Ref, 19),
                LEFT(s.DL_Design, 69),
                ?,                                      -- DL_Qte
                ?,                                      -- s.DL_QteBC,
                ?,                                      -- s.DL_QteBL,
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
                LEFT(s.EU_Enumere, 35),
                ?,                               -- EU_Qte
                s.DL_TTC,
                s.DE_No,
                s.DL_TypePL,
                s.DL_PUDevise,
                s.DL_PUTTC,
                ?,                                   -- NextDL_No
                s.DO_DateLivr,
                LEFT(s.CA_Num, 13),
                s.DL_Taxe3,
                s.DL_TypeTaux3,
                s.DL_TypeTaxe3,
                s.DL_Frais,
                s.DL_Valorise,
                LEFT(s.AR_RefCompose, 19),
                LEFT(s.AC_RefClient, 19),
                s.DL_MontantHT,
                s.DL_MontantTTC,
                s.DL_FactPoids,
                s.DL_Escompte,
                LEFT(s.DL_PiecePL, 13),
                s.DL_DatePL,
                ?,                                      -- DL_QtePL
                LEFT(s.RP_Code, 11),
                s.DL_QteRessource,
                s.DL_DateAvancement,
                LEFT(s.PF_Num, 9),
                LEFT(s.DL_CodeTaxe1, 5),
                LEFT(s.DL_CodeTaxe2, 5),
                LEFT(s.DL_CodeTaxe3, 5),
                s.DL_PieceOFProd,
                LEFT(s.DL_PieceDE, 13),
                s.DL_DateDE,
                s.DL_QteDE,
                LEFT(s.DL_Operation, 11),
                s.CA_No,
                s.DO_DocType,
                s.cbProt,
                LEFT(s.Nom, 69),
                s.Hauteur,
                s.Largeur,
                s.Profondeur,
                s.Langeur,
                LEFT(s.Couleur, 69),
                LEFT(s.Chant, 69),
                s.Episseur,
                LEFT(s.TRANSMIS, 50),
                LEFT(s.Poignée, 35),
                LEFT(s.Description, 35),
                LEFT(s.Rotation, 69)
            FROM F_DOCLIGNE s
            WHERE s.DL_No = ?                -- source DL_No
            ";

            DB::connection('sqlsrv')->insert($sql, [
                $CT_Num,     // CT_Num
                $DO_Piece,   // DO_Piece
                $DO_Date,    // DO_Date
                $DL_QteBL,
                $DL_QteBL,
                $DL_QteBL,
                $DL_QteBL,
                $nextDL_No,  // NextDL_No
                $DL_QteBL,
                $DL_No       // source DL_No
            ]);

            return $nextDL_No;
        } catch (Exception $e) {
            Log::error('Document line creation failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
