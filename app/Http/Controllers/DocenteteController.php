<?php

namespace App\Http\Controllers;

use App\Models\Action;
use App\Models\Docentete;
use App\Models\Docligne;
use App\Models\Document;
use App\Models\Line;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;
use App\Http\Controllers\SellController;
use App\Models\Article;
use App\Models\DocumentCompany;
use App\Models\Palette;
use App\Models\RoleQuantityLine;

class DocenteteController extends Controller
{

    public function preparation(Request $request)
    {
        $user_roles = auth()->user()->roles()->pluck('name', 'id');

        if ($user_roles->isEmpty()) {
            return response()->json([]);
        }

        $documents = Document::whereHas("lines", function ($query) use ($user_roles) {
            $line = $query->where("company_id", auth()->user()->company_id)

                ->whereIn('status_id', [1, 2, 3, 4, 5, 6, 7, 8, 9]);

            $common = array_intersect($user_roles->toArray(), ['fabrication', 'montage', 'preparation_cuisine', 'preparation_trailer', 'magasinier']);
            if (!empty($common)) {
                $line = $query->whereIn("role_id", $user_roles->keys());
            }
            return $line;
        });

        $query = Docentete::with(['document.status', 'document.companies'])
            ->select([
                'DO_Reliquat',
                'DO_Piece',
                'DO_Ref',
                'DO_Tiers',
                'cbMarq',
                'DO_Date',
                'DO_DateLivr',
                'DO_Expedit'
            ])
            ->whereIn("DO_Piece", $documents->pluck('piece'))
            ->orderByDesc("DO_Date")
            ->where('DO_Domaine', 0)
            ->where('DO_Statut', 1);


        if ($request->has('status')) {
            $query->where('DO_Type', $request->type);
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



    // Complation Fabriation & Montage
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
                'role_id' => $line->next_role_id ? $line->next_role_id : null,
                'next_role_id' => null,
                'status_id' => $line->next_role_id ? 7 : ($user->hasRole("fabrication") ? 4 : 6)
            ]);

            $line->update([
                'next_role_id' => null,
            ]);
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
            foreach ($request->lines as $line) {
                $line = Line::find($line);

                $line->update([
                    'complation_date' => $request->complation_date,
                    'status_id' => $user->hasRole("fabrication") ? 3 : 5
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
        $query = Docentete::query()
            ->select([
                'DO_Reliquat',
                'DO_Piece',
                'DO_Ref',
                'DO_Tiers',
                'cbMarq',
                'DO_Date',
                'DO_DateLivr',
                'DO_Expedit'
            ])
            ->orderByDesc("DO_Date")
            ->where('DO_Domaine', 0)
            ->where('DO_Statut', 1)
            ->where('DO_Type', $request->type ?? 2);

        $query->with('document.status');

        if (isset($request->status)) {

            if ($request->status == "0") {
                $query->whereDoesntHave('document');
            } elseif ($request->status == 1) {
                $query->whereHas('document', function ($q) {
                    $q->where('status_id', '>=', 1);
                });
            } else if ($request->status == 2) {
                $query->whereHas('document', function ($q) {
                    $q->where('status_id', '>=', 2);
                });
            } else {
                $query->whereHas('document', function ($q) use ($request) {
                    $q->where('status_id', $request->status);
                });
            }
        }


        if ($request->filled('date')) {
            $dates = explode(',', $request->date, 2);
            $start = Carbon::parse(urldecode($dates[0]))->startOfDay();
            $end = Carbon::parse(urldecode($dates[1] ?? $dates[0]))->endOfDay();

            $query->where(function ($query) use ($start, $end) {
                $query->whereDate('cbCreation', '>=', $start)
                    ->whereDate('cbCreation', '<=', $end);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('DO_Ref', 'like', "%$search%")
                    ->orWhere('DO_Piece', 'like', "%$search%")
                    ->orWhere('DO_Tiers', 'like', "%$search%")
                    ->orWhere('DO_Reliquat', 'like', "%$search%");
            });
        }

        // Faster pagination without total counts
        $results = $query->simplePaginate(30);

        return response()->json($results);
    }



    // Controller & Validation List
    public function validation(Request $request)
    {
        $query = Document::with([
            'companies',
            'docentete:cbMarq,DO_Date,DO_DateLivr,DO_Reliquat'
        ])
            ->whereHas('lines', function ($query) {
                $query->where('company_id', auth()->user()->company_id);
            })
            ->whereHas('companies', function ($query) {
                $query->whereIn('document_companies.status_id', [8, 9, 10]);
            });

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('ref', 'like', "%$search%")
                    ->orWhere('piece', 'like', "%$search%")
                    ->orWhere('client_id', 'like', "%$search%");
            });
        }

        $documents = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($documents);
    }



    public function validatePartial(Request $request, $piece)
    {
        if (!$request->user()->hasRole('controleur')) {
            return response()->json(["error" => "L'utilisateur n'est pas autorisé"], 401);
        }

        $lineData = $request->lines;
        $lineIds = [];
        $quantities = [];

        if (is_array($lineData) && !empty($lineData)) {
            if (is_array($lineData[0]) && isset($lineData[0]['line_id'])) {
                foreach ($lineData as $item) {
                    $lineIds[] = $item['line_id'];
                    $quantities[$item['line_id']] = $item['quantity'];
                }
            } else {
                // Old format - just array of line IDs
                $lineIds = $lineData;
            }
        }

        $lines = Line::whereIn('id', $lineIds)->get();

        $invalidLine = $lines->first(function ($line) {
            return !in_array($line->status_id, [8, 9, 10]);
        });

        if ($invalidLine) {
            return response()->json([
                'message' => "Article {$invalidLine->ref} n'a pas un status valide."
            ], 422);
        }

        $document = Document::where('piece', $piece)->first();

        if (!$document) {
            return response()->json(["error" => "Le document n'existe pas"], 404);
        }

        DB::transaction(function () use ($document, $lines) {


        $new_document = Document::create([
                'piece' => $document->piece,
                'type' => $document->type,
                'ref' => $document->ref,
                'expedition' => $document->expedition,
                'transfer_by' => $document->transfer_by,
                'validated_by' => auth()->id(),
                'controlled_by' => $document->controlled_by,
                'status_id' => 11,
                'client_id' => $document->client_id,
            ]);

            $palettes = [];

            foreach ($lines as $line) {
                $line->update([
                    'status_id'   => 11,
                    'document_id' => $new_document->id,
                ]);

                
                $palettes = array_merge($palettes, $line->palettes->pluck('id')->toArray());

               $new_document->companies()->syncWithoutDetaching([
                    $line->company_id => [
                        "status_id"     => 11,
                        "validated_by"  => auth()->id(),
                        "controlled_by" => auth()->id(),
                        "validated_at"  => now(),
                        "controlled_at" => now(),
                    ]
                ]);

    
            }

            if (!empty($palettes)) {
                Palette::whereIn('id', array_unique($palettes))
                    ->update(['document_id' => $new_document->id]);
            }



            




            // Achate d'article
            $sellController = new SellController();
            $sellController->calculator($document?->docentete->DO_Piece, $lines->pluck('docligne_id'));



            return response()->json(["message" => "Le document est validé avec succès"]);
        });
    }


    public function updateDocStatus($docentete)
    {
        try {

            $docentete->update(['DO_Statut' => 2]);
        } catch (\Exception $e) {
            throw $e;
        }
    }


    // Validate the Document
    public function validate(Request $request, $piece)
    {
        if (!$request->user()->hasRole('preparation')) {
            return response()->json(["error" => "L'utilisateur n'est pas autorisé"], 401);
        }

        $document = Document::where('piece', $piece)->first();

        if (!$document) {
            return response()->json(["error" => "Le document n'existe pas"], 404);
        }

        DB::transaction(function () use ($document) {
            foreach ($document->lines->where('company_id', auth()->user()->company_id) as $line) {
                $line->update(['status_id' => 11]);
            }

            $allLinesValidated = $document->lines
                ->every(fn($line) => $line->status_id == 11);

            if ($allLinesValidated) {
                $document->update([
                    'status_id' => 11,
                    'validated_by' => auth()->id()
                ]);

                // Update Docentete Status
                $this->updateDocStatus($document?->docentete);
            }

            $document->companies()->updateExistingPivot(auth()->user()->company_id, [
                'status_id' => 11,
                'validated_by' => auth()->id(),
                'validated_at' => now()
            ]);

            // Achate d'article
            $sellController = new SellController();
            $sellController->calculator($document?->docentete->DO_Piece);

            return response()->json(["message" => "Le document est validé avec succès"]);
        });
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
        $user = auth()->user();
        $userCompanyId = $user->company_id;
        $userRoles = $user->roles()->pluck('name')->toArray();
        $userRoleIds = $user->roles()->pluck('id')->toArray();

        $docentete = Docentete::with(['document.status', 'document.companies', 'document.palettes'])
            ->select(
                "DO_Piece",
                "DO_Ref",
                "DO_Tiers",
                "DO_Expedit",
                "cbMarq",
                "Type",
                "DO_Reliquat",
                "DO_Date",
                "DO_DateLivr"
            )
            ->where('DO_Piece', $id)
            ->firstOrFail();


        $docligneQuery = Docligne::with([
            'article' => function ($query) {
                $query->select("AR_Ref", "Nom", 'Hauteur', 'Largeur', 'Profonduer', 'Longueur', 'Couleur', 'Chant', 'Episseur', 'Description');
            },
            'line.palettes',
            'line.status',
            'line.roleQuantity',
            'stock' => function ($query) {
                $query->select('code', 'qte_inter', 'qte_serie');
            }
        ])

            ->select("DO_Piece", "AR_Ref", 'DL_Design', 'DL_Qte', "Nom", "Hauteur", "Largeur", "Profondeur", "Langeur", "Couleur", "Chant", "Episseur", "cbMarq", "DL_Ligne", 'Description', "Poignée as Poignee", "Rotation")
            ->OrderBy("DL_Ligne")
            ->where('DO_Piece', $id);

        if (in_array('preparation', $userRoles)) {

            $docligneQuery->whereHas('line', function ($query) use ($userCompanyId) {
                $query->where('company_id', $userCompanyId);
            });
        } elseif (array_intersect(['fabrication', 'montage', 'preparation_cuisine', 'preparation_trailer', 'magasinier'], $userRoles)) {

            $docligneQuery->whereHas('line', function ($query) use ($userCompanyId, $userRoleIds) {
                $query->where('company_id', $userCompanyId)
                    ->where(function ($subQuery) use ($userRoleIds) {
                        $subQuery->whereIn('role_id', $userRoleIds)
                            ->orWhereIn('next_role_id', $userRoleIds);
                    });
            });
        }

        // Execute with chunk processing for large datasets
        $doclignes = $docligneQuery->get();
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



    // Reset Document (Réinitialiser le document)
    public function reset($piece)
    {
        $document = Document::where("piece", $piece)->first();

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



    // Transfer to Role (Fabrication, Montage, Preparation)
    public function roleTransfer($request)
    {
        DB::beginTransaction();

        $request_roles = explode(',', $request->roles);

        if (count($request_roles) == 1) {
            $role = Role::find($request_roles[0]);
            $next_role = false;
        } else {
            $role = Role::find($request_roles[0]);
            $next_role = $request_roles[1];
        }

        if ($role->name  == 'fabrication') {
            $status = 3; // Fabrication
        } elseif ($role->name  == 'montage') {
            $status = 5; // Montage
        } elseif ($role->name  == 'magasinier') {
            $status = 7; // Preparation
        } elseif ($role->name  == "preparation_cuisine" || $role->name == "preparation_trailer") {
            $status = 7; // Preparation
        }

        try {
            $lineData = $request->lines;
            $lineIds = [];
            $quantities = [];

            if (is_array($lineData) && !empty($lineData)) {
                // Check if it's new format with objects containing line_id and quantity
                if (is_array($lineData[0]) && isset($lineData[0]['line_id'])) {
                    foreach ($lineData as $item) {
                        $lineIds[] = $item['line_id'];
                        $quantities[$item['line_id']] = $item['quantity'];
                    }
                } else {
                    // Old format - just array of line IDs
                    $lineIds = $lineData;
                }
            }

            $lines = Line::whereIn("id", $lineIds)->get();

            // Update status
            $document = $lines[0]->document;
            if ($lines->isNotEmpty()) {
                $document->update([
                    'status_id' => $status == 7 ? 7 : 2
                ]);
            }

            $document->companies()->updateExistingPivot(auth()->user()->company_id, [
                'status_id' => $status,
                'updated_at' => now(),
            ]);

            foreach ($lines as $line) {
                $updateData = [
                    'role_id' => intval($request->roles),
                    'status_id' => $status,
                    'next_role_id' => $next_role
                ];

                if (!empty($quantities) && isset($quantities[$line->id])) {
                    $updateData['transfer_quantity'] = $quantities[$line->id];

                    if (floatval($quantities[$line->id]) != floatval($line?->docligne?->DL_Qte)) {
                        RoleQuantityLine::create([
                            'line_id' => $line->id,
                            'quantity' => $quantities[$line->id],
                            'role_id' => $role->id

                        ]);
                    }
                }

                $line->update($updateData);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Document transferred successfully',
                'lines' => $lines,
                'quantities' => $quantities ?? null
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
                    'validated_by' => null,
                ]
            );

            if (!$document->companies()->where('company_id', $request->company)->exists()) {
                $document->companies()->attach($request->company, [
                    'status_id' => 1,
                    'updated_at' => now(),
                ]);
            }

            $lines = [];

            foreach ($request->lines as $lineId) {
                $currentDocligne = Docligne::where('cbMarq', $lineId)->first();

                $currentDocligne->DL_QteBL = 0;
                $currentDocligne->save();


                if (!$currentDocligne) {
                    throw new \Exception("Invalid line: {$lineId}");
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
                        'name' => $currentDocligne?->Nom,
                        'ref' => $currentDocligne->AR_Ref,
                        'design' => $currentDocligne->DL_Design,
                        'quantity' => $currentDocligne->DL_Qte,
                        'dimensions' => $currentDocligne->item,
                        'company_id' => $request->company,
                        'first_company_id'  => $request->company,
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


    // Transfer funciton controller
    public function transfer(Request $request)
    {
        $user = auth()->user();
        if ($user->hasRole("commercial")) {
            $validator = Validator::make($request->all(), [
                'company' => 'required|integer|exists:companies,id',
                'lines' => 'required|array'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $this->transferCompany($request);
        } else {
            $validator = Validator::make($request->all(), [
                'roles' => 'required',
                'lines' => 'required|array'
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            $this->roleTransfer($request);
        }
    }


    // Document list with pregress
    public function progress($piece)
    {
        $document = Document::with(['docentete.doclignes', 'lines.palettes'])->where("piece", $piece)->first();

        if (!$document) {
            return response()->json(["error" => "Document not found"], 404);
        }

        $required_qte = $document->lines->sum("quantity") ?? 0;

        $current_qte = 0;
        foreach ($document->lines as $line) {
            foreach ($line->palettes as $palette) {
                $current_qte += $palette->pivot->quantity;
            }
        }

        $progress = $required_qte > 0 ? round(($current_qte / $required_qte) * 100, 2) : 0;

        return response()->json([
            'current_qte' => $current_qte,
            'required_qte' => $required_qte,
            'progress' => intval($progress)
        ]);
    }



    //
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
            $query->where('DO_Type', $request->type);
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



    // Expidition List document
    public function shipping(Request $request)
    {
        $documents = Document::with('docentete')
            ->whereHas('docentete', function ($query) {
                $query->where('DO_Domaine', 0)
                    ->where('DO_Type', 3);
            })
            ->orderByDesc('created_at')
            ->get();

        return response()->json($documents);


        $documents = Document::whereHas('docentete', function ($query) {
            $query->where('DO_Statut', 2)
                ->where('DO_Domaine', 0)
                ->where('DO_Type', 3);
        })
            ->with([
                'docentete' => function ($query) {
                    $query->select('cbMarq', 'DO_Piece', 'DO_Type', 'DO_DateLivr', 'DO_Statut', 'DO_TotalHTNet', 'DO_TotalTTC');
                },
                'status'
            ])
            ->select('id', 'docentete_id', 'piece', 'type', 'ref', 'expedition', 'client_id', 'status_id')
            ->withCount('lines');

        // Apply search filter if provided
        if ($request->filled('search')) {
            $search = $request->search;
            $documents->where(function ($q) use ($search) {
                $q->where('piece', 'like', "%$search%")
                    ->orWhere('ref', 'like', "%$search%")
                    ->orWhere('client_id', 'like', "%$search%");
            });
        }

        // Apply date filter if provided
        if ($request->filled('date')) {
            try {
                $dates = explode(',', $request->date);
                $start = Carbon::parse(urldecode($dates[0]))->startOfDay();
                $end = Carbon::parse(urldecode($dates[1]))->endOfDay();

                $documents->whereBetween('created_at', [$start, $end]);
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Invalid date format provided.'
                ], 422);
            }
        }

        // Paginate results
        $results = $documents->paginate(20);

        return response()->json($results);
    }
}
