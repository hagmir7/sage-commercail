<?php

namespace App\Http\Controllers;

use App\Models\ArticleStock;
use App\Models\CompanyStock;
use App\Models\Docentete;
use App\Models\Docligne;
use App\Models\Document;
use App\Models\Emplacement;
use App\Models\Line;
use App\Models\Palette;
use App\Models\StockMovement;
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
        $query->when($request->has('status') && $request->status !== '', function ($q) use ($request) {
            if ((int) $request->status === 0) {
                $q->whereDoesntHave('document');
            } else {
                $q->whereHas('document', fn($sub) => $sub->where('status_id', (int) $request->status));
            }
        });



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

    public function generatePaletteCode()
    {
        $lastCode = DB::table('palettes')
            ->where('code', 'like', 'PALS%')
            ->orderBy('id', 'desc')
            ->value('code');

        if (!$lastCode) {
            $nextNumber = 1;
        } else {
            $number = (int) substr($lastCode, 4);
            $nextNumber = $number + 1;
        }
        return 'PALS' . str_pad($nextNumber, 8, '0', STR_PAD_LEFT);
    }



    public function show(Request $request, $piece)
    {
        return Docentete::on($request->company)
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
            ->with(['doclignes:DO_Piece,AR_Ref,DL_Design,DL_Qte,DL_QteBL,DL_Ligne,cbMarq,DL_Qte', 'doclignes.line', 'doclignes.line.user_role', 'document'])
            ->find($piece);
    }

    public function transfer(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'document_piece' => 'required',
            'user_id' => 'required|exists:users,id',
            'lines' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
                'message' => $validator->errors()->first()
            ], 422);
        }


        try {
            $user = User::find($request->user_id);

            $docentete = Docentete::on($request->company)->with('doclignes')->findOrFail($request->document_piece);

            if (!$docentete) {
                throw new \Exception("Le document avec la référence $request->document_piece n'existe pas");
            }
            $document = Document::on($request->company)->firstOrCreate(
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
            foreach ($request->lines as $lineId) {
                $docligne = Docligne::on($request->company)->where('cbMarq', $lineId)->first();

                $docligne->update([
                    'DL_QteBL' => floatval(0),
                    'cbModification' => now()->format('Y-m-d H:i:s')
                ]);

                if ($docligne->AR_Ref != null) {
                    $line = Line::on($request->company)->firstOrCreate([
                        'docligne_id' => $docligne->cbMarq,
                    ], [
                        'docligne_id' => $docligne->cbMarq,
                        'tiers' => $docligne->CT_Num,
                        'name' => $docligne?->Nom,
                        'ref' => $docligne?->AR_Ref,
                        'design' => $docligne->DL_Design,
                        'quantity' => $docligne->DL_Qte,
                        'dimensions' => $docligne->item,
                        'company_id' => $user->company_id,
                        'document_id' => $document->id,
                        'role_id' => $user->id
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


    public function list(Request $request)
    {
        $companies = ['sqlsrv', 'sqlsrv_inter', 'sqlsrv_serie', 'sqlsrv_asti'];
        $allDocentetes = collect();

        foreach ($companies as $company) {
            try {
                // Fetch data from each connection
                $query = Docentete::on($company)
                    ->whereHas('doclignes.line', function ($q) {
                        $q->where('role_id', auth()->id());
                    })
                    ->with([
                        'doclignes' => function ($q) {
                            $q->select(['DO_Piece', 'cbMarq']);
                        },
                        'doclignes.line' => function ($q) {
                            $q->select(['id', 'role_id', 'docligne_id']);
                        },
                        'compt' => function ($q) {
                            $q->select(['CT_Intitule', 'CT_Num', 'cbMarq', 'CT_Telephone']);
                        },
                        'document' => function ($q) {
                            $q->select(['id', 'docentete_id', 'status_id']);
                        },
                    ])
                    ->select([
                        'DO_Reliquat',
                        'DO_Piece',
                        'DO_Ref',
                        'DO_Tiers',
                        'cbMarq',
                        'DO_Date',
                        'DO_DateLivr',
                        'DO_Expedit',
                        'DO_TotalHT',
                        'cbCreation'
                    ])
                    ->orderByDesc('cbCreation')
                    ->get();

                // Add company origin to each record
                $query->each(function ($doc) use ($company) {
                    $doc->company = $company;
                });

                // Merge results
                $allDocentetes = $allDocentetes->merge($query);
            } catch (\Throwable $e) {
                \Log::error("❌ Failed fetching data from {$company}: " . $e->getMessage());
                continue; // Skip failed connection
            }
        }

        // Sort all merged results by cbCreation (descending)
        $allDocentetes = $allDocentetes->sortByDesc('cbCreation')->values();

        // --- Manual Pagination Helper ---
        $perPage = 30;
        $page = $request->get('page', 1);
        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $allDocentetes->forPage($page, $perPage),
            $allDocentetes->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return response()->json($paginated);
    }



    public function reset($piece, Request $request)
    {
        $document = Document::on($request->company)->where("piece", $piece)->first();

        if (!$document) {
            return response()->json(['error' => "Document does not exist"], 404);
        }

       

        if (!auth()->user()->hasRole("commercial")) {
            return response()->json(['error' => "Unauthorized"], 403);
        }

         foreach ($document->lines as $line) {
            $line->delete();
        }


        $document->delete();

        return response()->json(['message' => "Réinitialisé avec succès"], 200);
    }


    public function validation(Request $request, $piece)
    {
        // $document = Document::on($request->company_db)->where("piece", $piece)->first();
        $document = \App\Models\Document::on($request->company_db)->where('piece', $piece)->first();
        if (!$document) {
            return response()->json(['message' => "Le document n'existe pas $piece"], 404);
        }



        if (!auth()->user()->hasRole("admin")) {
            return response()->json(['message' => "Unauthorized"], 403);
        }

        $document->status_id = 3;
        $document->save();

        return response()->json(['message' => "Réinitialisé avec succès"], 200);
    }


    public function movement(Request $request, $piece)
    {
        try {
            $validator = Validator::make($request->all(), [
                'emplacement_code' => 'required|string|max:255|min:3|exists:emplacements,code',
                'code_article'     => 'required|string',
                'quantity'         => 'required|numeric|min:0',
                'condition'        => 'nullable|numeric|min:1',
                'type_colis'       => 'nullable|in:Piece,Palette,Carton',
                'palettes'         => 'required_if:type_colis,Palette,Carton|numeric|min:1',
                'company'          => 'required|numeric'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors()->first(),
                    'errors'  => $validator->errors()
                ], 422);
            }


            $companyId   = intval($request->company ?? 1);
            $article     = ArticleStock::where('code', $request->code_article)->first();
            $emplacement = Emplacement::where("code", $request->emplacement_code)->first();

            if (!$article) {
                return response()->json([
                    'errors' => ['article' => 'Article non trouvé']
                ], 404);
            }

            if (!$emplacement) {
                return response()->json([
                    'errors' => ['emplacement' => 'Emplacement non trouvé']
                ], 404);
            }

            $conditionMultiplier = $request->condition ? (float) $request->condition : 1.0;

            $docentete = Docentete::on($request->company_db)->find($piece);


            $docligne = $docentete->doclignes()->where("AR_Ref", $request->code_article)->first();

            if (!$docligne || $docligne->DL_Qte < ($request->quantity + $docligne->DL_QteBL)) {
                return response()->json(['message' => "Quantité insuffisante"], 422);
            }


            DB::transaction(function () use ($article, $request, $conditionMultiplier, $emplacement, $companyId, $docligne, $docentete) {
                StockMovement::create([
                    'code_article'     => $request->code_article,
                    'designation'      => $article->description,
                    'emplacement_id'   => $emplacement->id,
                    'movement_type'    => "IN",
                    'article_stock_id' => $article->id,
                    'quantity'         => $request->quantity,
                    'moved_by'         => auth()->id(),
                    'company_id'       => $companyId,
                    'movement_date'    => now(),
                ]);

                $docligne->update([
                    'DL_QteBL' => ($docligne->DL_QteBL + floatval($request->quantity))
                ]);

                if ($docentete->doclignes->sum('DL_QteBL') == $docentete->doclignes->sum('DL_Qte')) {
                    $docentete->document()->update([
                        'status_id' => 2
                    ]);
                }

                if ($request->type_colis === "Palette" || $request->type_colis === "Carton") {
                    $qte_value = $request->palettes * $conditionMultiplier;
                } else {
                    $qte_value = $request->quantity;
                }

                $company_stock = CompanyStock::where('code_article', $request->code_article)
                    ->where('company_id', $companyId)
                    ->first();

                if ($company_stock) {
                    $company_stock->quantity += $qte_value;
                    $company_stock->save();
                } else {
                    CompanyStock::create([
                        'code_article' => $request->code_article,
                        'designation'  => $article->description,
                        'company_id'   => $companyId,
                        'quantity'     => $qte_value
                    ]);
                }

                // ✅ Update emplacement pivot safely
                $existing = $emplacement->articles()->find($article->id);

                if ($existing) {
                    $emplacement->articles()->updateExistingPivot($article->id, [
                        'quantity' => DB::raw('quantity + ' . $qte_value)
                    ]);
                } else {
                    $emplacement->articles()->attach($article->id, ['quantity' => $qte_value]);
                }

                // ✅ Handle palettes
                if ($request->type_colis === "Palette") {
                    for ($i = 1; $i <= intval($request->quantity); $i++) {
                        $palette = Palette::create([
                            "code"           => $this->generatePaletteCode(),
                            "emplacement_id" => $emplacement->id,
                            "company_id"     => $companyId,
                            "user_id"        => auth()->id(),
                            "type"           => "Stock",
                        ]);
                        $article->palettes()->attach($palette->id, ['quantity' => floatval($conditionMultiplier)]);
                    }
                } else {
                    // One palette per emplacement
                    $palette = Palette::firstOrCreate(
                        ["emplacement_id" => $emplacement->id],
                        [
                            "code"       => $this->generatePaletteCode(),
                            "company_id" => $companyId,
                            "user_id"    => auth()->id(),
                            "type"       => "Stock"
                        ]
                    );

                    if ($article->palettes()->where('palette_id', $palette->id)->exists()) {
                        $article->palettes()->updateExistingPivot(
                            $palette->id,
                            ['quantity' => DB::raw('quantity + ' . (int) $qte_value)]
                        );
                    } else {
                        $article->palettes()->attach($palette->id, ['quantity' => $qte_value]);
                    }
                }
            });

            return response()->json(['message' => 'Stock successfully inserted or updated.']);
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::error('Database error in insert function', [
                'error'        => $e->getMessage(),
                'sql'          => $e->getSql() ?? 'N/A',
                'bindings'     => $e->getBindings() ?? [],
                'trace'        => $e->getTraceAsString(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'message' => 'Une erreur de base de données s\'est produite.',
                'error'   => 'Database error'
            ], 500);
        } catch (\Exception $e) {
            \Log::error('Unexpected error in insert function', [
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'line'         => $e->getLine(),
                'file'         => $e->getFile()
            ]);

            return response()->json([
                'message' => 'Une erreur inattendue s\'est produite.',
                'error'   => 'Internal server error'
            ], 500);
        }
    }
}
