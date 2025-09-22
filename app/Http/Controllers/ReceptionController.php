<?php

namespace App\Http\Controllers;

use App\Models\Docentete;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReceptionController extends Controller
{


   public function index(Request $request)
    {
        $query = Docentete::on($request->company)
            ->with('document');

        if ($request->status == 1) {
            $query->whereHas('document');
        }

        $docentetes = $query->select([
                'DO_Reliquat',
                'DO_Piece',
                'DO_Ref',
                'DO_Tiers',
                'cbMarq',
                'DO_Date',
                'DO_DateLivr',
                'DO_Expedit',
            ])
            ->where('DO_Type', 12)
            ->where('DO_Domaine', 1)
            ->paginate(30);

        return $docentetes;
    }



    public function show(){

    }


    


    //  return DB::connection('sqlsrv_inter')
    //         ->table('F_DOCENTETE')
    //         ->where('DO_Type', 12)
    //         ->where('DO_Domaine', 1)
    //         ->get();

}
