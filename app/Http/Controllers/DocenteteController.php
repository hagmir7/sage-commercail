<?php

namespace App\Http\Controllers;

use App\Models\Docentete;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocenteteController extends Controller
{

    public function index($status)
    {
        return DB::select(
            "SELECT DO_Reliquat, DO_Piece, DO_Ref, DO_Tiers, CONVERT(VARCHAR(10), DO_Date, 111) AS DO_Date, CONVERT(VARCHAR(10), DO_DateLivr, 111) AS DO_DateLivr, DO_Expedit 
            FROM F_DOCENTETE WHERE DO_Domaine = 0 AND DO_Type = 2 AND DO_Statut = 1"
        );
    }


    public function show($id)
    {
        $docentete = Docentete::with('doclignes')->find($id);
        return response(json_encode($docentete, JSON_INVALID_UTF8_IGNORE), 200, ['Content-Type' => 'application/json']);
    }
}
