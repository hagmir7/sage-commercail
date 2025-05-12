<?php

namespace App\Http\Controllers;

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
                $document = Document::create([
                    'docentete_id' => $docentete->cpMarq,
                    'piece' => $docentete->DO_Piece,
                    'type' => $docentete->Type,
                    'transfer_by' => 1, // optionally use auth()->id()
                    'ref' => $docentete->DO_Ref,
                    'expedition' => $docentete->DO_Expedit,
                    'completed' => false
                ]);
            }
    
            // Create lines
            $lines = [];
            foreach ($request->lines as $lineId) {
                $currentDocligne = Docligne::where('cbMarq', $lineId)->first();
    
                if (!$currentDocligne) {
                    throw new \Exception("Invalid line: {$lineId}");
                }
    
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
