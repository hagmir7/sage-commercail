<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use DateTime;
use Exception;

class WebOrderController extends Controller
{
    private const DO_TYPE          = 0;
    private const DO_DOMAINE       = 0;
    private const DO_STATUT        = 2;
    private const CB_CREATION_USER = '69C8CD64-D06F-4097-9CAC-E488AC2610F9';

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function generateHeure(): string
    {
        return str_pad((new DateTime())->format('His'), 9, '0', STR_PAD_LEFT);
    }

    private function generatePiece(): string
    {
        $lastPiece = DB::connection('sqlsrv')
            ->table('F_DOCCURRENTPIECE')
            ->where('cbMarq', 1)
            ->value('DC_Piece') ?? '26DE000000';

        preg_match('/^([A-Z0-9]+DE)(\d{6})$/i', $lastPiece, $matches);

        $newPiece = count($matches) === 3
            ? $matches[1] . str_pad((int) $matches[2] + 1, 6, '0', STR_PAD_LEFT)
            : '26DE000001';

        DB::connection('sqlsrv')
            ->table('F_DOCCURRENTPIECE')
            ->where('cbMarq', 1)
            ->update(['DC_Piece' => $newPiece]);

        return $newPiece;
    }

    private function getDate(): string
    {
        return DB::connection('sqlsrv')
            ->selectOne('SELECT CAST(CAST(GETDATE() AS DATE) AS DATETIME) as [current_date]')
            ->current_date;
    }

    private function getNextDLNo(): int
    {
        return DB::connection('sqlsrv')
            ->selectOne('SELECT ISNULL(MAX(DL_No), 0) + 1 AS NextDL_No FROM F_DOCLIGNE')
            ->NextDL_No;
    }

    // Trick : passer par JSON pour strip les bytes invalides
    private function fixEncoding(?string $value): string
    {
        if ($value === null) return '';

        $clean = json_encode($value, JSON_INVALID_UTF8_IGNORE);
        return json_decode($clean) ?? '';
    }
    // ─── Main entry point ─────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $request->validate([
            'order_code'                  => 'required|string',
            'customer_code'               => 'required|string',
            'total_ht'                    => 'required|numeric',
            'total_ttc'                   => 'required|numeric',
            'products'                    => 'required|array|min:1',
            'products.*.code'             => 'required|string',
            'products.*.designation'      => 'required|string',
            'products.*.quantity'         => 'required|integer|min:1',
            'products.*.unit_price'       => 'required|numeric|min:0',
            'products.*.discount'         => 'nullable|numeric|min:0|max:100',
            'products.*.discounted_price' => 'nullable|numeric|min:0',
            'products.*.total'            => 'required|numeric|min:0',
            'products.*.height'           => 'nullable|numeric',
            'products.*.width'            => 'nullable|numeric',
            'products.*.depth'            => 'nullable|numeric',
            'products.*.color'            => 'nullable|string',
        ]);

        // Fix encoding on all string fields of every product
        $products = array_map(function (array $product) {
            return array_map(function ($value) {
                return is_string($value) ? $this->fixEncoding($value) : $value;
            }, $product);
        }, $request->products);

        $orderCode    = $request->order_code;
        $customerCode = $request->customer_code;
        $totalHT      = floatval($request->total_ht);
        $totalTTC     = floatval($request->total_ttc);

        DB::connection('sqlsrv')->beginTransaction();

        try {
            $DO_Piece = $this->generatePiece();
            $DO_Date  = $this->getDate();

            $this->createDocentete($orderCode, $customerCode, $DO_Piece, $DO_Date, $totalHT, $totalTTC);

            foreach ($products as $product) {
                $this->createDocligne($product, $DO_Piece, $DO_Date, $orderCode, $customerCode);
            }

            DB::connection('sqlsrv')->commit();

            return response()->json([
                'status'  => 'success',
                'message' => "Commande transférée avec succès ({$DO_Piece})",
                'piece'   => $DO_Piece,
            ]);

        } catch (Exception $e) {
            DB::connection('sqlsrv')->rollBack();

            $errorMsg = mb_check_encoding($e->getMessage(), 'UTF-8')
                ? $e->getMessage()
                : mb_convert_encoding($e->getMessage(), 'UTF-8', 'Windows-1252');

            Log::error('WebOrder DE transfer failed', [
                'order_code' => $orderCode,
                'error'      => $errorMsg,
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Erreur lors du transfert: ' . $errorMsg,
            ], 500);
        }
    }

    // ─── Document header ──────────────────────────────────────────────────────

    private function createDocentete(
        string $orderCode,
        string $customerCode,
        string $DO_Piece,
        string $DO_Date,
        float  $totalHT,
        float  $totalTTC
    ): void {
        DB::connection('sqlsrv')->table('F_DOCENTETE')->insert([
            'DO_Domaine'     => self::DO_DOMAINE,
            'DO_Type'        => self::DO_TYPE,
            'DO_Piece'       => $DO_Piece,
            'DO_Date'        => $DO_Date,
            'DO_Ref'         => $orderCode,
            'DO_Tiers'       => $customerCode,
            'CO_No'          => 0,
            'cbCreationUser' => self::CB_CREATION_USER,
            'cbModification' => DB::raw('GETDATE()'),
            'cbCreation'     => DB::raw('GETDATE()'),
            'DO_Statut'      => self::DO_STATUT,
            'CT_NumPayeur'   => $customerCode,
            'DO_Period'      => 1,
            'DO_Devise'      => 1,
            'DO_Cours'       => floatval(1),
            'LI_No'          => 0,
            'DO_Expedit'     => 1,
            'DO_NbFacture'   => 1,
            'DO_BLFact'      => 0,
            'DO_TxEscompte'  => floatval(0),
            'DO_Reliquat'    => 0,
            'DO_Imprim'      => 0,
            'DO_Souche'      => 0,
            'DO_DateLivr'    => $DO_Date,
            'DO_Condition'   => 1,
            'DO_Tarif'       => 1,
            'DO_Colisage'    => 1,
            'DO_TypeColis'   => 1,
            'DO_Transaction' => 11,
            'DO_Langue'      => 0,
            'DO_Ecart'       => floatval(0),
            'DO_Regime'      => 11,
            'N_CatCompta'    => 5,
            'DO_Ventile'     => 0,
            'AB_No'          => 0,
            'CG_Num'         => 44110000,
            'DO_Heure'       => $this->generateHeure(),
            'CA_No'          => 0,
            'CO_NoCaissier'  => 0,
            'DO_Transfere'   => 0,
            'DO_Cloture'     => 0,
            'DO_Attente'     => 0,
            'DO_Provenance'  => 0,
            'DO_TotalHTNet'  => $totalHT,
            'DO_TotalTTC'    => $totalTTC,
            'DO_NetAPayer'   => $totalTTC,
            'Montant_S_DT'   => $totalTTC,
        ]);
    }

    // ─── Document line ────────────────────────────────────────────────────────

    private function createDocligne(
        array  $product,
        string $DO_Piece,
        string $DO_Date,
        string $orderCode,
        string $customerCode
    ): void {
        $nextDL_No   = $this->getNextDLNo();
        $discount    = floatval($product['discount'] ?? 0);
        $qty         = intval($product['quantity']);
        $arRef       = substr($product['code'], 0, 19);

        $article     = Article::where('AR_Ref', $arRef)->first();

        // Use the already-fixed $product['designation'] (encoding fixed upstream)
        $designation = substr(preg_replace('/\s+/', ' ', trim($product['designation'])), 0, 69);

        $priceNet = round($article->AR_PrixVen * (1 - $discount / 100), 2);
        $priceTTC = round($priceNet * 1.20, 2);

        DB::connection('sqlsrv')->table('F_DOCLIGNE')->insert([
            'DO_Domaine'            => self::DO_DOMAINE,
            'DO_Type'               => self::DO_TYPE,
            'CT_Num'                => $customerCode,
            'DO_Piece'              => $DO_Piece,
            'DO_Date'               => $DO_Date,
            'DO_Ref'                => $orderCode,
            'DL_Ligne'              => 0,
            'DL_TNomencl'           => 0,
            'DL_TRemPied'           => 0,
            'DL_TRemExep'           => 0,
            'AR_Ref'                => $arRef,
            'DL_Design'             => $designation,           // cleaned + truncated
            'DL_Qte'                => $qty,
            'DL_QteBC'              => $qty,
            'DL_QteBL'              => $qty,
            'DL_PoidsNet'           => 0,
            'DL_PoidsBrut'          => 0,
            'DL_PrixRU'             => 0,
            'DL_Remise01REM_Type'   => 1,
            'DL_Remise02REM_Valeur' => 0,
            'DL_Remise02REM_Type'   => 0,
            'DL_Remise03REM_Valeur' => 0,
            'DL_Remise03REM_Type'   => 0,
            'DL_Remise01REM_Valeur' => $discount,
            'DL_PrixUnitaire'       => $article->AR_PrixVen,
            'DL_Taxe1'              => 20,
            'DL_PUBC'               => $article->AR_PrixVen,
            'DL_PUDevise'           => $priceNet,
            'DL_PUTTC'              => $priceTTC,
            'DL_MontantHT'          => round($priceNet * $qty, 2),
            'DL_MontantTTC'         => round($priceTTC * $qty, 2),
            'DL_TypeTaux1'          => 0,
            'DL_TypeTaxe1'          => 0,
            'DL_Taxe2'              => 0,
            'DL_TypeTaux2'          => 0,
            'DL_TypeTaxe2'          => 0,
            'DL_Taxe3'              => 0,
            'DL_TypeTaux3'          => 0,
            'DL_TypeTaxe3'          => 0,
            'CO_No'                 => 0,
            'AG_No1'                => 0,
            'AG_No2'                => 0,
            'DL_NoRef'              => 1,
            'DL_CMUP'               => 0,
            'DL_MvtStock'           => 0,
            'DT_No'                 => 0,
            'AF_RefFourniss'        => null,
            'EU_Enumere'            => $article?->unit?->cbIndice,
            'EU_Qte'                => $qty,
            'DL_TTC'                => 0,
            'DE_No'                 => 1,
            'DL_TypePL'             => 0,
            'DL_No'                 => $nextDL_No,
            'DO_DateLivr'           => $DO_Date,
            'CA_Num'                => '',
            'DL_Frais'              => 0,
            'DL_Valorise'           => 1,
            'AR_RefCompose'         => null,
            'AC_RefClient'          => null,
            'DL_FactPoids'          => 0,
            'DL_Escompte'           => 0,
            'DL_PiecePL'            => null,
            'DL_DatePL'             => $DO_Date,
            'DL_QtePL'              => $qty,
            'RP_Code'               => null,
            'DL_QteRessource'       => 0,
            'DL_DateAvancement'     => $DO_Date,
            'PF_Num'                => '',
            'DL_CodeTaxe1'          => 'C20',
            'DL_CodeTaxe2'          => null,
            'DL_CodeTaxe3'          => null,
            'DL_PieceOFProd'        => 0,
            'DL_PieceDE'            => null,
            'DL_DateDE'             => $DO_Date,
            'DL_QteDE'              => 0,
            'DL_Operation'          => null,
            'CA_No'                 => 0,
            'DO_DocType'            => 0,
            'cbProt'                => 0,
            'DL_NonLivre'           => 0,
            'cbCreationUser'        => self::CB_CREATION_USER,
            'cbModification'        => DB::raw('GETDATE()'),
            'cbCreation'            => DB::raw('GETDATE()'),
            'Hauteur'               => floatval($product['height'] ?? 0),
            'Largeur'               => floatval($product['width']  ?? 0),
            'Profondeur'            => floatval($product['depth']  ?? 0),
            'Langeur'               => 0,
            'Couleur'               => substr($product['color'] ?? '', 0, 69),
            'Chant'                 => null,
            'Episseur'              => 0,
            'TRANSMIS'              => null,
            'Rotation'              => null,
            'DL_NoColis'            => 0,
        ]);
    }
}