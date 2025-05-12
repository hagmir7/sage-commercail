<?php

namespace App\Http\Controllers;

use App\Models\Docentete;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\json;

class DocenteteController extends Controller
{

    public function index($status)
    {
        return DB::select(
            "SELECT DO_Reliquat, DO_Piece, DO_Ref, DO_Tiers, cbMarq, CONVERT(VARCHAR(10), DO_Date, 111) AS DO_Date, CONVERT(VARCHAR(10), DO_DateLivr, 111) AS DO_DateLivr, DO_Expedit
            FROM F_DOCENTETE WHERE DO_Domaine = 0 AND DO_Type = 2 AND DO_Statut = 1"
        );
    }



    function getDoclignes($query) {}


    public function show($id)
    {
        try {
            $docentete = Docentete::with(['doclignes' => function ($query) {
                $query->select(
                    "DO_Piece",
                    "AR_Ref",
                    'DL_Qte',
                    "Nom",
                    "Hauteur",
                    "Largeur",
                    "Profondeur",
                    "Langeur",
                    "Couleur",
                    "Chant",
                    "Episseur",
                    "cbMarq"

                );
            }])
                ->select(
                    "DO_Piece",
                    "DO_Ref",
                    "DO_Tiers",
                    "DO_Expedit",
                    "cbMarq",
                    "Type",
                    "DO_Reliquat",
                    DB::raw("CONVERT(VARCHAR(10), DO_Date, 111) AS DO_Date"),
                    DB::raw("CONVERT(VARCHAR(10), DO_DateLivr, 111) AS DO_DateLivr")
                )
                ->findOrFail($id);
            return response(json_encode($docentete, JSON_INVALID_UTF8_IGNORE), 200, ['Content-Type' => 'application/json']);
        } catch (\Throwable $th) {
            return response()->json(["message" => 'Document is not found'], 404);
        }
    }
}
