<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use DateTime;
use Exception;

class WebOrderController extends Controller
{
    private const DO_TYPE    = 12; // DE — adjust to your Sage DO_Type for Demande
    private const DO_DOMAINE = 0;  // Achats — adjust if needed
    private const DO_STATUT  = 2;
    private const CB_CREATION_USER = '69C8CD64-D06F-4097-9CAC-E488AC2610F9';

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function generateHeure(): string
    {
        $now = new DateTime();
        return str_pad($now->format('His'), 9, '0', STR_PAD_LEFT);
    }

    private function generatePiece(): string
    {
        $lastPiece = DB::connection('sqlsrv')
            ->table('F_DOCENTETE')
            ->where('DO_Type', self::DO_TYPE)
            ->where('DO_Piece', 'LIKE', '%DE%')
            ->orderByDesc('DO_Piece')
            ->value('DO_Piece') ?? '26DE000000';

        preg_match('/^([A-Z0-9]+DE)(\d{6})$/i', $lastPiece, $matches);

        if (count($matches) === 3) {
            return $matches[1] . str_pad((int) $matches[2] + 1, 6, '0', STR_PAD_LEFT);
        }

        return '26DE000001';
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

    // ─── Main entry point ────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $request->validate([
            'order_id'   => 'required|integer|exists:orders,id',
        ]);

        $order = Order::with([
            'user',
            'items.product',
            'items.dimension.attribute',
            'items.dimension.color',
        ])->findOrFail($request->order_id);

        if ($order->items->isEmpty()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Cette commande ne contient aucun article.',
            ], 422);
        }

        DB::connection('sqlsrv')->beginTransaction();

        try {
            $DO_Piece = $this->generatePiece();
            $DO_Date  = $this->getDate();

            $totalHT  = 0;
            $totalTTC = 0;

            // Pre-calculate totals
            foreach ($order->items as $item) {
                $unitPrice   = floatval($item->discounted_price ?? $item->unit_price ?? ($item->dimension?->price ?? $item->product->price));
                $discount    = floatval($item->discount_percent ?? 0);
                $priceNet    = round($unitPrice * (1 - $discount / 100), 2);
                $qty         = intval($item->quantity);

                $totalHT  += round($priceNet * $qty, 2);
                $totalTTC += round($priceNet * 1.2, 2) * $qty;
            }

            // 1. Create F_DOCENTETE
            $this->createDocentete($order, $DO_Piece, $DO_Date, $totalHT, $totalTTC);

            // 2. Create F_DOCLIGNE for each item
            foreach ($order->items as $item) {
                $this->createDocligne($item, $DO_Piece, $DO_Date, $order);
            }

            DB::connection('sqlsrv')->commit();

            // Mark order as confirmed in your local DB
            $order->update(['sage_piece' => $DO_Piece]);

            return response()->json([
                'status'   => 'success',
                'message'  => "Commande transférée avec succès ({$DO_Piece})",
                'piece'    => $DO_Piece,
            ]);

        } catch (Exception $e) {
            DB::connection('sqlsrv')->rollBack();
            Log::error('WebOrder DE transfer failed', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);

            return response()->json([
                'status'  => 'error',
                'message' => 'Erreur lors du transfert: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ─── Create document header ───────────────────────────────────────────────

    private function createDocentete(Order $order, string $DO_Piece, string $DO_Date, float $totalHT, float $totalTTC): void
    {
        $customerCode = $order->user->code ?? 'CLIENT001';

        DB::connection('sqlsrv')->table('F_DOCENTETE')->insert([
            'DO_Domaine'     => self::DO_DOMAINE,
            'DO_Type'        => self::DO_TYPE,
            'DO_Piece'       => $DO_Piece,
            'DO_Date'        => $DO_Date,
            'DO_Ref'         => $order->code,           // web order code as reference
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
            'DO_TotalHTNet'  => round($totalHT, 2),
            'DO_TotalTTC'    => round($totalTTC, 2),
            'DO_NetAPayer'   => round($totalTTC, 2),
            'Montant_S_DT'   => round($totalTTC, 2),
        ]);
    }

    // ─── Create document line ─────────────────────────────────────────────────

    private function createDocligne($item, string $DO_Piece, string $DO_Date, Order $order): void
    {
        $nextDL_No    = $this->getNextDLNo();
        $customerCode = $order->user->code ?? 'CLIENT001';

        $unitPrice   = floatval($item->discounted_price ?? $item->unit_price ?? ($item->dimension?->price ?? $item->product->price));
        $discount    = floatval($item->discount_percent ?? 0);
        $priceNet    = round($unitPrice * (1 - $discount / 100), 2);
        $priceTTC    = round($priceNet * 1.2, 2);
        $qty         = intval($item->quantity);

        $arRef       = $item->dimension?->code ?? $item->product->code;

        $designation = trim(
            ($item->dimension?->attribute?->name ?? '') . ' ' .
            str_replace('Façade', '', $item->product->name ?? '') . ' ' .
            ($item->dimension?->dimension ?? '') . ' ' .
            ($item->dimension?->color?->name ?? '')
        );
        $designation = preg_replace('/\s+/', ' ', $designation);

        DB::connection('sqlsrv')->table('F_DOCLIGNE')->insert([
            'DO_Domaine'              => self::DO_DOMAINE,
            'DO_Type'                 => self::DO_TYPE,
            'CT_Num'                  => $customerCode,
            'DO_Piece'                => $DO_Piece,
            'DO_Date'                 => $DO_Date,
            'DO_Ref'                  => $order->code,
            'DL_Ligne'                => 0,
            'DL_TNomencl'             => 0,
            'DL_TRemPied'             => 0,
            'DL_TRemExep'             => 0,
            'AR_Ref'                  => substr($arRef, 0, 19),
            'DL_Design'               => substr($designation, 0, 69),
            'DL_Qte'                  => $qty,
            'DL_QteBC'                => $qty,
            'DL_QteBL'                => $qty,
            'DL_PoidsNet'             => 0,
            'DL_PoidsBrut'            => 0,
            'DL_Remise01REM_Valeur'   => $discount,
            'DL_Remise01REM_Type'     => 0,
            'DL_Remise02REM_Valeur'   => 0,
            'DL_Remise02REM_Type'     => 0,
            'DL_Remise03REM_Valeur'   => 0,
            'DL_Remise03REM_Type'     => 0,
            'DL_PrixUnitaire'         => $unitPrice,
            'DL_PUBC'                 => $unitPrice,
            'DL_Taxe1'                => 20,        // TVA 20%
            'DL_TypeTaux1'            => 1,
            'DL_TypeTaxe1'            => 1,
            'DL_Taxe2'                => 0,
            'DL_TypeTaux2'            => 0,
            'DL_TypeTaxe2'            => 0,
            'DL_Taxe3'                => 0,
            'DL_TypeTaux3'            => 0,
            'DL_TypeTaxe3'            => 0,
            'CO_No'                   => 0,
            'AG_No1'                  => 0,
            'AG_No2'                  => 0,
            'DL_PrixRU'               => 0,
            'DL_CMUP'                 => 0,
            'DL_MvtStock'             => 1,
            'DT_No'                   => 0,
            'AF_RefFourniss'          => '',
            'EU_Enumere'              => '',
            'EU_Qte'                  => $qty,
            'DL_TTC'                  => 0,
            'DE_No'                   => 0,
            'DL_TypePL'               => 0,
            'DL_PUDevise'             => $priceNet,
            'DL_PUTTC'                => $priceTTC,
            'DL_No'                   => $nextDL_No,
            'DO_DateLivr'             => $DO_Date,
            'CA_Num'                  => '',
            'DL_Frais'                => 0,
            'DL_Valorise'             => 1,
            'AR_RefCompose'           => '',
            'AC_RefClient'            => '',
            'DL_MontantHT'            => round($priceNet * $qty, 2),
            'DL_MontantTTC'           => round($priceTTC * $qty, 2),
            'DL_FactPoids'            => 0,
            'DL_Escompte'             => 0,
            'DL_PiecePL'              => '',
            'DL_DatePL'               => $DO_Date,
            'DL_QtePL'                => $qty,
            'RP_Code'                 => '',
            'DL_QteRessource'         => 0,
            'DL_DateAvancement'       => $DO_Date,
            'PF_Num'                  => '',
            'DL_CodeTaxe1'            => '',
            'DL_CodeTaxe2'            => '',
            'DL_CodeTaxe3'            => '',
            'DL_PieceOFProd'          => 0,
            'DL_PieceDE'              => '',
            'DL_DateDE'               => $DO_Date,
            'DL_QteDE'                => 0,
            'DL_Operation'            => '',
            'CA_No'                   => 0,
            'DO_DocType'              => 0,
            'cbProt'                  => 0,
            'cbCreationUser'          => self::CB_CREATION_USER,
            'cbModification'          => DB::raw('GETDATE()'),
            'cbCreation'              => DB::raw('GETDATE()'),

            // Physical dimensions from order item
            'Nom'                     => substr($item->product->name ?? '', 0, 69),
            'Hauteur'                 => $item->special_height ?? $item->dimension?->height ?? 0,
            'Largeur'                 => $item->special_width  ?? $item->dimension?->width  ?? 0,
            'Profondeur'              => $item->dimension?->dipth ?? 0,
            'Langeur'                 => 0,
            'Couleur'                 => substr($item->dimension?->color?->name ?? '', 0, 69),
            'Chant'                   => '',
            'Episseur'                => $item->dimension?->thicknesse ?? 0,
            'TRANSMIS'                => '',
            'Poignée'                 => '',
            'Description'             => substr($designation, 0, 35),
            'Rotation'                => '',
            'DL_NoColis'              => 0,
        ]);
    }
}