<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Docentete;
use App\Models\Docligne;
use App\Models\Document;
use App\Models\Line;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DocenteteController extends Controller
{

    public function preparation(Request $request)
    {
        $documents = Document::whereHas("lines", function($query){
            $query->where("company_id", auth()->user()->company_id);
        });
        $query = Docentete::query()
            ->select([
                'DO_Reliquat',
                'DO_Piece',
                'DO_Ref',
                'DO_Tiers',
                'cbMarq',
                \DB::raw("CONVERT(VARCHAR(10), DO_Date, 111) AS DO_Date"),
                \DB::raw("CONVERT(VARCHAR(10), DO_DateLivr, 111) AS DO_DateLivr"),
                'DO_Expedit'
            ])
            ->whereIn("DO_Piece", $documents->pluck('piece'))
            ->orderByDesc("DO_Date")
            ->where('DO_Domaine', 0)
            ->where('DO_Statut', 1);


        if ($request->has('status')) {
            $query->where('DO_Type', $request->status);
        } else {
            $query->where('DO_Type', 2);
        }

        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('DO_Reliquat', 'like', "%$search%")
                    ->orWhere('DO_Piece', 'like', "%$search%")
                    ->orWhere('DO_Ref', 'like', "%$search%")
                    ->orWhere('DO_Tiers', 'like', "%$search%");
            });
        }


        $results = $query->paginate(20);

        return response()->json($results);
    }


    public function commercial(Request $request)
    {
        $query = Docentete::query()
            ->select([
                'DO_Reliquat',
                'DO_Piece',
                'DO_Ref',
                'DO_Tiers',
                'cbMarq',
                \DB::raw("CONVERT(VARCHAR(10), DO_Date, 111) AS DO_Date"),
                \DB::raw("CONVERT(VARCHAR(10), DO_DateLivr, 111) AS DO_DateLivr"),
                'DO_Expedit'
            ])
            ->orderByDesc("DO_Date")
            ->where('DO_Domaine', 0)
            ->where('DO_Statut', 1);


        if ($request->has('status')) {
            $query->where('DO_Type', $request->status);
        } else {
            $query->where('DO_Type', 2);
        }

        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('DO_Reliquat', 'like', "%$search%")
                    ->orWhere('DO_Piece', 'like', "%$search%")
                    ->orWhere('DO_Ref', 'like', "%$search%")
                    ->orWhere('DO_Tiers', 'like', "%$search%");
            });
        }


        $results = $query->paginate(20);

        return response()->json($results);
    }





    public function show($id)
    {

        // $document = Document::with('lines')->where("piece", $id)->first();
        // dd($document->lines->count());
        $docentete = Docentete::select(
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
            ->where('DO_Piece', $id)
            ->firstOrFail();

        // Build the doclignes query
        $docligneQuery = Docligne::with(['line' => function ($query) {
            $query->select('id', 'company_id', 'docligne_id', 'role_id');
        }])
            ->where('DO_Piece', $id)
            ->select("DO_Piece", "AR_Ref", 'DL_Qte', "Nom", "Hauteur", "Largeur", "Profondeur", "Langeur", "Couleur", "Chant", "Episseur", "cbMarq");

        // Apply role-based filtering if needed
        if (auth()->user()->hasRole(['preparateur'])) {
            $docligneQuery->whereHas('line', function ($query) {
                $query->where('company_id', auth()->user()->company_id);
            });
        }

        // Execute the query
        $doclignes = $docligneQuery->get();

        return response()->json([
            'docentete' => $docentete,
            'doclignes' => $doclignes
        ], 200);
    }



  public function roleTransfer($request)
    {
        DB::beginTransaction();
        try {
            $lines = Line::whereIn("id", $request->lines)->get();

            foreach ($lines as $line) {
                $line->update([
                    'role_id' => intval($request->transfer)
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Document transferred successfully',
                'lines' => $lines
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }




    public function transferCompany($request){

        DB::beginTransaction();
        try {
            $docligne = Docligne::with('docentete')->where('cbMarq', $request->lines[0])->first();

            if (!$docligne || !$docligne->docentete) {
                throw new \Exception('Invalid document line or header');
            }

            $docentete = Docentete::where("cbMarq", intval($docligne->docentete->cbMarq))->first();

            if (!$docentete) {
                throw new \Exception('Docentete not found');
            }

            $document = Document::firstOrCreate(
                ['docentete_id' => $docentete->cbMarq],
                [
                    'piece' => $docentete->DO_Piece,
                    'type' => $docentete->Type,
                    'transfer_by' => 1,
                    'ref' => $docentete->DO_Ref,
                    'client_id' => $docentete->DO_Tiers,
                    'expedition' => $docentete->DO_Expedit,
                    'completed' => false
                ]
            );


            $lines = [];
            foreach ($request->lines as $lineId) {
                $currentDocligne = Docligne::where('cbMarq', $lineId)->first();

                if ($currentDocligne && $currentDocligne->AR_Ref !== null && $currentDocligne->AR_Ref !== "SP000001") {
                    $article = Article::find($currentDocligne->AR_Ref);



                    if ($article) {
                        DB::connection('sqlsrv')->unprepared("
                            SET NOCOUNT ON;
                            SET XACT_ABORT ON;
                            DISABLE TRIGGER ALL ON F_DOCLIGNE;

                            UPDATE F_DOCLIGNE
                            SET Nom = '" . addslashes($article->Nom) . "',
                                Hauteur = " . ($article->Hauteur !== null ? floatval($article->Hauteur) : "NULL") . ",
                                Largeur = " . ($article->Largeur !== null ? floatval($article->Largeur) : "NULL") . ",
                                Profondeur = " . ($article->Profondeur !== null && $article->Profondeur !== '' ? floatval($article->Profondeur) : "NULL") . ",
                                Chant = '" . addslashes($article->Chant) . "',
                                Episseur = " . ($article->Episseur !== null ? floatval($article->Episseur) : "NULL") . "
                            WHERE cbMarq = '" . addslashes($lineId) . "';

                            ENABLE TRIGGER ALL ON F_DOCLIGNE;
                        ");
                    }
                }



                if (!$currentDocligne) {
                    throw new \Exception("Invalid line: {$lineId}");
                }

                if($currentDocligne->AR_Ref != null){
                    $line = Line::firstOrCreate([
                        'docligne_id' => $currentDocligne->cbMarq,
                    ],[
                        'tiers' => $currentDocligne->item,
                        'ref' => $currentDocligne->AR_Ref,
                        'design' => $currentDocligne->DL_Design,
                        'quantity' => $currentDocligne->DL_Qte,
                        'dimensions' => $currentDocligne->item,
                        'company_id' => $request->company,
                        'document_id' => $document->id,

                    ]);
                }
                $lines[] = $line;
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Document transferred successfully',
                'document' => $document,
                'lines' => $lines
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }



    public function transfer(Request $request)
    {

        $user = auth()->user();

        // if (!$user->hasRole("commercial") || !$user->hasRole("preparateur")) {
        //     return response()->json(["message" => "Unauthorized user"], 403);
        // }

        if ($user->hasRole("commercial")) {
            $validator = Validator::make($request->all(), [
                'transfer' => 'required|integer|exists:companies,id',
                'lines' => 'required|array'
            ]);

            $this->transferCompany($request);
            

            
        } else {
            $validator = Validator::make($request->all(), [
                'transfer' => 'required|integer|exists:roles,id',
                'lines' => 'required|array'
            ]);

            $this->roleTransfer($request);

        }

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

    }

}
