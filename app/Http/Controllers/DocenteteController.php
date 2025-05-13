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


    public function index(Request $request)
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
            ->findOrFail($id);

        $docligne = Docligne::with(['line' => function ($query) {
            $query->select('company_id', 'docligne_id')->get();
        }])
            ->where('DO_Piece', $id)
            ->select("DO_Piece", "AR_Ref", 'DL_Qte', "Nom", "Hauteur", "Largeur", "Profondeur", "Langeur", "Couleur", "Chant", "Episseur", "cbMarq")
            ->get();

        return response()->json([
            'docentete' => $docentete,
            'doclignes' => $docligne
        ], 200);
    }



    public function transfer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company' => 'required|integer|exists:companies,id',
            'lines' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Find the first document line
            $docligne = Docligne::where('cbMarq', $request->lines[0])->first();

            if (!$docligne || !$docligne->docentete) {
                throw new \Exception('Invalid document line or header');
            }

            // Get the Docentete using cbMarq
            $docentete = Docentete::where("cbMarq", intval($docligne->docentete->cbMarq))->first();

            if (!$docentete) {
                throw new \Exception('Docentete not found');
            }

            // Check if document already exists using UUID
            $document = Document::where('docentete_id', $docentete->id)->first();

            // Create document if it doesn't exist
            if (!$document) {
                        DB::statement("SET DATEFORMAT ymd");
                        DB::statement("SET LANGUAGE English");
                $document = Document::create([
                    'docentete_id' => intval($docentete->cbMarq),
                    'piece' => $docentete->DO_Piece,
                    'type' => $docentete->Type,
                    'transfer_by' => 1, // optionally use auth()->id()
                    'ref' => $docentete->DO_Ref,
                    'client_id' => $docentete->DO_Tiers,
                    'expedition' => $docentete->DO_Expedit,
                    'completed' => false
                ]);
            }

            // Create lines
            // Nom, Hauteur, Largeur, Profondeur, Langeur, Couleur, Chant, Episseur, TRANSMIS, Poignée, Description, Rotation

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
                    $line = Line::create([
                        'tiers' => $currentDocligne->item,
                        'ref' => $currentDocligne->AR_Ref,
                        'design' => $currentDocligne->DL_Design,
                        'quantity' => $currentDocligne->DL_Qte,
                        'dimensions' => $currentDocligne->item,
                        'company_id' => $request->company,
                        'document_id' => $document->id,
                        'docligne_id' => $currentDocligne->cbMarq,
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

}
