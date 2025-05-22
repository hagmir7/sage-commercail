<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;


use Illuminate\Http\Request;

class SellController extends Controller
{
    public function create(){
        DB::table('F_DOCENTETE')->insertUsing([
            'DO_DocType', 'DO_Devise', 'DO_Domaine', 'DO_Type', 'DO_Statut', 'DO_Piece', 'DO_Date',
            'DO_Ref', 'DO_Tiers', 'CO_No', 'DO_Period', 'DO_Cours', 'DE_No', 'cbDE_No', 'LI_No',
            'CT_NumPayeur', 'DO_Expedit', 'DO_NbFacture', 'DO_BLFact', 'DO_TxEscompte', 'DO_Reliquat',
            'DO_Imprim', 'CA_Num', 'DO_Coord01', 'DO_Coord02', 'DO_Coord03', 'DO_Coord04', 'DO_Souche',
            'DO_DateLivr', 'DO_Condition', 'DO_Tarif', 'DO_Colisage', 'DO_TypeColis', 'DO_Transaction',
            'DO_Langue', 'DO_Ecart', 'DO_Regime', 'N_CatCompta', 'DO_Ventile', 'AB_No', 'DO_DebutAbo',
            'DO_FinAbo', 'DO_DebutPeriod', 'DO_FinPeriod', 'CG_Num', 'DO_Heure', 'CA_No', 'CO_NoCaissier',
            'DO_Transfere', 'DO_Cloture', 'DO_NoWeb', 'DO_Attente', 'DO_Provenance', 'CA_NumIFRS',
            'MR_No', 'DO_TypeFrais', 'DO_ValFrais', 'DO_TypeLigneFrais', 'DO_TypeFranco',
            'DO_ValFranco', 'DO_TypeLigneFranco', 'DO_Taxe1', 'DO_TypeTaux1', 'DO_TypeTaxe1',
            'DO_Taxe2', 'DO_TypeTaxe2', 'DO_Taxe3', 'DO_TypeTaux3', 'DO_TypeTaxe3', 'DO_MajCpta',
            'DO_Motif', 'DO_Contact', 'DO_FactureElec', 'DO_TypeTransac', 'DO_DateLivrRealisee',
            'DO_DateExpedition', 'DO_FactureFrs', 'DO_PieceOrig', 'DO_EStatut', 'DO_DemandeRegul',
            'ET_No', 'DO_Valide', 'DO_Coffre', 'DO_StatutBAP', 'DO_Escompte', 'DO_TypeCalcul',
            'DO_MontantRegle', 'DO_AdressePaiement', 'DO_PaiementLigne', 'DO_MotifDevis',
            'DO_Conversion', 'DO_TypeTaux2', 'DO_TotalHTNet', 'DO_TotalTTC', 'DO_NetAPayer',
            'Montant_S_DT', 'cbCreationUser'
        ], DB::table('F_DOCENTETE')
            ->selectRaw("?, ?, ?, ?, ?, ?, CONVERT(DATETIME, CONVERT(DATE, GETDATE())), ?, ?, 0, 1, 1, 1, 1, 0, ?, 1, 1, 0, 0, 0, 0, '', '', '', '', '', CASE WHEN ? = 'FR001' THEN DO_Souche ELSE 0 END, DO_DateLivr,
                1, 1, 1, 1, 11, 0, 0, 11, 5, 0,
                0, DO_DebutAbo, DO_FinAbo, DO_DebutPeriod, DO_FinPeriod, 44110000, ?, 0, 0, 0,
                0, '', 0, 0, '', 0, 0, 0, 0, 0, 0,
                0, 0, 0, 0, 0, 0, 0, 0, 0,
                0, '', '', 0, 0, DO_DateLivrRealisee, DO_DateExpedition, '', '', 0, 0,
                0, 0, 0, 0, 0, 0, 0, '', 0, 0, 0, 0, ?, ?, ?, ?, '69C8CD64-D06F-4097-9CAC-E488AC2610F9'",
                [
                    13, 1, 1, 13, 0, $doPiece, $doRef, $doTiers, $doTiers,
                    $doTiers, $doTiers, $doHeure,
                    $mtEntrer, $mtEntrerTTC, $mtEntrerTTC, $mtEntrerTTC
                ]
            )
            ->where('DO_Domaine', 0)
            ->where('DO_Type', 2)
            ->where('DO_Piece', $p));

            }


    public function createDoc(string $doPieceGen, string $doPiece, string $fournisseur, string $scte)
    {
        try {
            DB::beginTransaction();

            // Assume calculerMontant() returns [totalHT, totalTTC]
            [$totalHT, $totalTTC] = $this->calculerMontant($doPiece, $this->getDistinctLignes($doPiece, $scte));

            $query = "YOUR SQL QUERY HERE"; // Replace with actual SQL query

            $affected = DB::update($query, [
                'P'             => $doPiece,
                'DoPiece'       => $doPieceGen,
                'DoTiers'       => $fournisseur,
                'DoHeure'       => $this->getTime(),
                'DoRef'         => $this->getPiecBC($doPiece),
                'mtEntrer'      => str_replace(',', '.', str_replace('.', '', $totalHT)),
                'mtEntrerTTC'   => str_replace(',', '.', str_replace('.', '', $totalTTC)),
            ]);

            if ($affected > 0) {
                $this->recData($doPiece, $doPieceGen, $scte, $fournisseur);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            // Log::error("Erreur lors de la création du document : " . $e->getMessage());
            return response()->json(['error' => 'Erreur : ' . $e->getMessage()], 500);
        }
    }



    private $queryRec = "SELECT * FROM F_DOCLIGNE WHERE DO_Piece = @theDopiece;";

    public function recData(string $oldReference, string $newReference, string $scte, string $fournisseur)
    {
        $results = DB::select($this->queryRec, [
            'theDopiece' => $oldReference,
        ]);

        foreach ($results as $row) {
            $arRef = $row->AR_Ref;
            $dlLigne = $row->DL_Ligne;
            $dlQteBL = $row->DL_QteBL;

            if (
                $this->getSct($oldReference, $arRef, $dlLigne) === $scte &&
                $this->checkingEtatLivraison($oldReference, $arRef, $dlLigne)
            ) {
                $this->addLigne($oldReference, $newReference, $dlLigne, $arRef, $dlQteBL, $fournisseur);
            }
        }
    }
}
