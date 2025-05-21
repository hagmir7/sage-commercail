<?php

namespace App\Http\Controllers;

use App\Models\Action;
use App\Models\Article;
use App\Models\Docentete;
use App\Models\Docligne;
use App\Models\Document;
use App\Models\Line;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DocenteteController extends Controller
{

    public function preparation(Request $request)
    {
        $user_roles = auth()->user()->roles()->pluck('name', 'id');

        if ($user_roles->isEmpty()) {
            return response()->json([]);
        }

        $documents = Document::whereHas("lines", function ($query) use ($user_roles) {
            $line = $query->where("company_id", auth()->user()->company_id);

            $common = array_intersect($user_roles->toArray(), ['fabrication', 'montage', 'preparation_cuisine']);
            if (!empty($common)) {
                $line = $query->whereIn("role_id", $user_roles->keys());
            }

            return $line;
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


    public function complation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lines' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = auth()->user();

        foreach ($request->lines as $line) {
            $line = Line::find($line);

            $line->update([
                'completed' => true,
                'role_id' => $line->next_role_id || null,
                'status_id' => $line->next_role_id ? 7 : ($user->hasRole("fabrication") ? 4 : 6) // 4 For Fabrication & 6 for Montage
            ]);

            if ($user->hasRole('fabrication')) {
                $action = Action::where("line_id", $line->id)->where('action_type_id', 4); // Action for fabrication
            } elseif ($user->hasRole('montage')) {
                $action = Action::where("line_id", $line->id)->where('action_type_id', 5); // Action for fabrication
            }

            try {
                $action->update(['end' => now()]);
            } catch (\Throwable $th) {
                //throw $th;
            }
        }

        return response()->json($request->all(), 200);
    }



    public function start(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'complation_date'  => "required",
            'lines' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = auth()->user();

        if (!$user->hasRole("fabrication") ||  !$user->hasRole("montage")) {
            foreach ($request->lines as $line){
                $line = Line::find($line);

                $line->update([
                    'complation_date' => $request->complation_date,
                    'status_id' => $user->hasRole("fabrication") ? 3 : 5 // 3 For Fabrication & 5 for Montage
                ]);

                Action::create([
                    'user_id' => auth()->id(),
                    'action_type_id' =>  $user->hasRole("fabrication") ? 4 : 5,
                    'line_id' => $line->id,
                    'description' => "Fabrication",
                    'start' => now(),
                ]);
            }
            return response()->json(['message' => "Date updated successfully"]);
        }

        return response()->json(['error' => "Your Have no access"], 500);
    }


    public function commercial(Request $request)
    {
        // dd($user_roles);
        $query = Docentete::with('document')
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


    public function validation(Request $request)
    {
        $query = Docentete::with('document')
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
            ->whereHas('document', function ($query) {
                $query->where('completed', 1);
            })
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


    public function validate(Request $request, $piece){
        $validator = Validator::make($request->all(), [
            'lines' => "required|array"
        ]);

        if($validator->fails()){
            return response()->json(["errors" => $validator->errors()], 500);
        }

        $document = Document::where('piece', $piece)->first();

        if(!$document){
            return response()->json(["error" => "Document is not definded"], 404);
        }

        foreach($request->lines as $line){
            $line = Line::find($line);
            $line->update([
                'validated' => true
            ]);
        }

        return response()->json(["message" => "Lines are verifide"]);
    }


    public function competedPalettes($piece)
    {
        $document = Document::where('piece', $piece)->with('lines.palettes')->first();

        if (!$document) {
            return response()->json(['status' => false, 'message' => 'Document not found'], 404);
        }

        $invalidLines = [];

        foreach ($document->lines as $line) {
            // Sum all quantities from pivot table for this line
            $totalPaletteQuantity = $line->palettes->sum(function ($palette) {
                return $palette->pivot->quantity ?? 0;
            });

            // Compare with required_quantity
            if ($totalPaletteQuantity < $line->quantity) {
                $invalidLines[] = [
                    'line_id' => $line->id,
                    'quantity' => $line->quantity,
                    'total_palette_quantity' => $totalPaletteQuantity
                ];
            }
        }

        if (count($invalidLines)) {
            return response()->json([
                'status' => false,
                'message' => 'Some lines do not meet the required quantity from palettes.',
                'invalid_lines' => $invalidLines
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'All lines have sufficient palette quantities.'
        ]);
    }





    public function show($id)
    {
        $docentete = Docentete::with('document')->select(
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

        $docligne = Docligne::with(['article' => function ($query) {
            $query->select("Nom", 'Hauteur', 'Largeur', 'Profonduer', 'Longueur', 'Couleur',  'Chant', 'Episseur', 'Description', 'AR_Ref');
        }, 'line' => function ($query) { $query->with('palettes')->select('id', 'company_id', 'docligne_id', 'role_id', 'completed', 'complation_date', 'validated','status_id');
        }, 'stock' => function($query){
            $query->select('code', 'qte_inter', 'qte_serie');
        }])
            ->select("DO_Piece", "AR_Ref", 'DL_Design', 'DL_Qte', "Nom", "Hauteur", "Largeur", "Profondeur", "Langeur", "Couleur", "Chant", "Episseur", "cbMarq")
            ->where('DO_Piece', $id);


        if (auth()->user()->hasRole(['preparateur'])) {

            $docligne->whereHas('line', function ($query) {
                $query->where('company_id', auth()->user()->company_id);
            });

        } elseif (auth()->user()->hasRole(['fabrication', 'montage'])) {
            $user_roles = auth()->user()->roles()->pluck('id');
            $docligne->whereHas('line', function ($query) use ($user_roles) {
                $query->where('company_id', auth()->user()->company_id)
                    ->whereIn('role_id', $user_roles);
            });
        }

        // Execute the query
        $doclignes = $docligne->get();

        return response()->json([
            'docentete' => $docentete,
            'doclignes' => $doclignes
        ], 200);
    }



    public function showTest($id)
    {
        $document = Document::with([
            'lines' => function ($query) {
                $query->with(['palettes', 'docligne'])
                    ->select('id', 'document_id', 'ref', 'name', 'quantity', 'design', 'role_id', 'complation_date', 'status_id', 'company_id');
            }
        ])
            ->where('piece', $id)
            ->select('id', 'piece', 'ref', 'expedition', 'client_id', 'status_id')
            ->first();

        return response()->json($document);
    }






    // Transfer to Role (Fabrication, Montage, Preparation)
    public function roleTransfer($request)
    {
        DB::beginTransaction();


        $role = Role::find($request->transfer);


        if ($role->name  == 'fabrication') {
            $status = 3;
        } elseif ($role->name  == 'montage') {
            $status = 5;
        } elseif ($role->name  == 'magasinier') {
            $status = 7; // Preparation
        } elseif ($role->name  == "preparation_cuisine" || $role->name == "preparation_trailer") {
            $status = 7;
        }

        try {
            $lines = Line::whereIn("id", $request->lines)->get();

            foreach ($lines as $line) {
                $line->update([
                    'role_id' => intval($request->transfer),
                    'status_id' => $status
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


    // Transfer to Company controller (Adill)
    public function transferCompany($request)
    {
        try {
            $docligne = Docligne::where('cbMarq', $request->lines[0])->first();

            if (!$docligne || !$docligne->docentete) {
                throw new \Exception('Invalid document line or header');
            }

            $docentete = Docentete::where('cbMarq', intval($docligne->docentete->cbMarq))->first();

            if (!$docentete) {
                throw new \Exception('Docentete not found');
            }

            $document = Document::firstOrCreate(
                ['docentete_id' => $docentete->cbMarq],
                [
                    'piece' => $docentete->DO_Piece,
                    'type' => $docentete->Type,
                    'transfer_by' => auth()->id(),
                    'ref' => $docentete->DO_Ref,
                    'client_id' => $docentete->DO_Tiers,
                    'expedition' => $docentete->DO_Expedit,
                    'completed' => false,
                ]
            );

            $lines = [];

            foreach ($request->lines as $lineId) {
                $currentDocligne = Docligne::where('cbMarq', $lineId)->first();
                if (!$currentDocligne) {
                    throw new \Exception("Invalid line: {$lineId}");
                }
                if ($currentDocligne->AR_Ref && $currentDocligne->AR_Ref !== 'SP000001') {
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

                if ($currentDocligne->AR_Ref != null) {
                    $line = Line::firstOrCreate([
                        'docligne_id' => $currentDocligne->cbMarq,
                    ], [
                        'docligne_id' => $currentDocligne->cbMarq,
                        'tiers' => $currentDocligne->CT_Num,
                        'ref' => $currentDocligne->AR_Ref,
                        'design' => $currentDocligne->DL_Design,
                        'quantity' => $currentDocligne->DL_Qte,
                        'dimensions' => $currentDocligne->item,
                        'company_id' => $request->transfer,
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


    public function transfer(Request $request)
    {
        $user = auth()->user();


        if ($user->hasRole("commercial")) {
            $validator = Validator::make($request->all(), [
                'transfer' => 'required|integer|exists:companies,id',
                'lines' => 'required|array'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $this->transferCompany($request);
        } else {
            $validator = Validator::make($request->all(), [
                'transfer' => 'required|integer|exists:roles,id',
                'lines' => 'required|array'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $this->roleTransfer($request);
        }
    }


    public function fabrication(Request $request)
    {

        $user_roles = auth()->user()->roles()->pluck('name', 'id');

        if ($user_roles->isEmpty()) {
            return response()->json([]);
        }

        $documents = Document::whereHas("lines", function ($query) use ($user_roles) {
            $query->where("company_id", auth()->user()->company_id);

            $common = array_intersect($user_roles->toArray(), ['fabrication', 'montage']);
            if (!empty($common)) {
                $query->whereIn("role_id", $user_roles->keys());
            }
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
}
