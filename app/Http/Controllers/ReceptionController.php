<?php

namespace App\Http\Controllers;

use App\Models\Docentete;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReceptionController extends Controller
{
    public function index(Request $request)
    {
        $query = Docentete::on($request->company)
            ->with(['document', 'compt:CT_Intitule,CT_Num,cbMarq,CT_Telephone']);

        // Filter by status
        // if ($request->filled('status')) {
        //     if ($request->status == 1) {
        //         $query->whereHas('document');
        //     } else {
        //         $query->where('DO_Statut', $request->status); 
        //     }
        // }

        // Filter by type
        if ($request->filled('status')) {
            $query->whereHas('document', function ($document) use ($request) {
                $document->where("status_id", $request->status);
            });
        }

        // Filter by domain (fixed)
        $query->where('DO_Domaine', 1)->where("DO_Type", 12)->where('DO_Statut', 2);

        // Multiple search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('DO_Piece', 'like', "%{$search}%")
                    ->orWhere('DO_Ref', 'like', "%{$search}%")
                    ->orWhere('DO_Tiers', 'like', "%{$search}%")
                    ->orWhereHas('compt', function ($sub) use ($search) {
                        $sub->where('CT_Intitule', 'like', "%{$search}%")
                            ->orWhere('CT_Telephone', 'like', "%{$search}%")
                            ->orWhere('CT_Num', 'like', "%{$search}%");
                    });
            });
        }

        // Date range filter
        if ($request->filled('date')) {
            $dates = explode(',', $request->date, 2);
            $start = Carbon::parse(urldecode($dates[0]))->startOfDay();
            $end = Carbon::parse(urldecode($dates[1] ?? $dates[0]))->endOfDay();

            $query->where(function ($query) use ($start, $end) {
                $query->whereDate('cbCreation', '>=', $start)
                    ->whereDate('cbCreation', '<=', $end);
            });
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
            
        ])->orderByDesc('cbCreation')
            ->paginate(30);

        return response()->json($docentetes);
    }





    public function show() {}





    //  return DB::connection('sqlsrv_inter')
    //         ->table('F_DOCENTETE')
    //         ->where('DO_Type', 12)
    //         ->where('DO_Domaine', 1)
    //         ->get();

}
