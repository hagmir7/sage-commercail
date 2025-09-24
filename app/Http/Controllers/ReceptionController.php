<?php

namespace App\Http\Controllers;

use App\Models\ArticleStock;
use App\Models\Docentete;
use App\Models\Docligne;
use App\Models\Document;
use App\Models\Line;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ReceptionController extends Controller
{
    public function index(Request $request)
    {
        $query = Docentete::on($request->company)
            ->with(['document', 'compt:CT_Intitule,CT_Num,cbMarq,CT_Telephone']);


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
            'DO_TotalHT'

        ])->orderByDesc('cbCreation')
            ->paginate(30);

        return response()->json($docentetes);
    }



    public function show($piece)
    {
        return Docentete::on('sqlsrv_inter')
            ->select(
                'DO_Reliquat',
                'DO_Piece',
                'DO_Ref',
                'DO_Tiers',
                'cbMarq',
                'DO_Date',
                'DO_DateLivr',
                'DO_Expedit',
                'DO_TotalHT'
            )
            ->with(['doclignes:DO_Piece,AR_Ref,DL_Design,DL_Qte,DL_QteBL,DL_Ligne,cbMarq', 'doclignes.line',])
            ->find($piece);
    }

    public function transfer(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'document_piece' => 'required',
            'user_id' => 'required|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
                'message' => $validator->errors()->first()
            ], 422);
        }


        try {
            $user = User::find($request->user_id);


            $docentete = Docentete::on('sqlsrv_inter')->with('doclignes')->findOrFail($request->document_piece);

            if (!$docentete) {
                throw new \Exception("Le document avec la référence $request->document_piece n'existe pas");
            }
            $document = Document::on('sqlsrv_inter')->firstOrCreate(
                ['docentete_id' => $docentete->cbMarq],
                [

                    'piece' => (string) $docentete->DO_Piece,
                    'transfer_by' => auth()->id(),
                    'type' => "Document Achate",
                    'ref' => (string) $docentete->DO_Ref,
                    'client_id' => (string) $docentete->DO_Tiers,
                    'expedition' => (string) $docentete->DO_Expedit,
                    'created_at' => now()->format('Y-m-d H:i:s'),
                    'updated_at' => now()->format('Y-m-d H:i:s')
                ]
            );


            $lines = [];
            foreach ($docentete->doclignes as $docligne) {

                if ($docligne) {
                    $docligne->DL_QteBL = "0";
                    $docentete->cbModification = now()->format('Y-m-d H:i:s');
                    $docligne->save();
                }

                if ($docligne->AR_Ref != null) {
                    $line = Line::on('sqlsrv_inter')->firstOrCreate([
                        'docligne_id' => $docligne->cbMarq,
                    ], [
                        'docligne_id' => $docligne->cbMarq,
                        'tiers' => $docligne->CT_Num,
                        'name' => $docligne?->Nom,
                        'ref' => $docligne->AR_Ref,
                        'design' => $docligne->DL_Design,
                        'quantity' => $docligne->DL_Qte,
                        'dimensions' => $docligne->item,
                        'company_id' => $user->company_id,
                        'document_id' => $document->id,

                    ]);
                }


                $lines[] = $line;
            }
            return response()->json([
                'status'  => 'success',
                'message' => 'Document transferred successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function reset($piece)
    {
        $document = Document::on('sqlsrv_inter')->where("piece", $piece)->first();

        if (!$document) {
            return response()->json(['error' => "Document does not exist"], 404);
        }

        foreach ($document->lines as $line) {
            $line->delete();
        }


        if (!auth()->user()->hasRole("commercial")) {
            return response()->json(['error' => "Unauthorized"], 403);
        }

        $document->delete();

        return response()->json(['message' => "Réinitialisé avec succès"], 200);
    }
}
