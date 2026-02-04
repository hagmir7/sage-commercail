<?php

namespace App\Http\Controllers;

use App\Models\ArticleStock;
use App\Models\Docentete;
use App\Models\Docligne;
use App\Models\Document;
use App\Models\DocumentReception;
use App\Models\Emplacement;
use App\Models\Line;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\StockMovementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Symfony\Component\Clock\now;

class ReceptionController extends Controller
{

    protected $stockService;

    // Add constructor to inject StockService
    public function __construct(StockMovementService $stockService)
    {
        $this->stockService = $stockService;
    }

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
                    // 'created_at' => now()->format('Y-m-d H:i:s'),
                    // 'updated_at' => now()->format('Y-m-d H:i:s')
                ]
            );


            $lines = [];
            foreach ($request->lines as $lineId) {
                $docligne = Docligne::on($request->company)->where('cbMarq', $lineId)->first();

                $docligne->update([
                    'DL_QteBL' => floatval(0),
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
                continue;
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

        foreach ($document->receptions as $reception) {
            $reception->delete();
        }


        $document->delete();

        return response()->json(['message' => "Réinitialisé avec succès"], 200);
    }


    public function validation(Request $request, $piece)
    {
        if (!auth()->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            // Start transaction on the correct connection
            DB::connection($request->company_db)->beginTransaction();

            $document = Document::on($request->company_db)
                ->with([
                    'receptions' => function ($query) {
                        $query->with(['article', 'emplacement']);
                    }
                ])
                ->where('piece', $piece)
                ->first();

            if (!$document) {
                return response()->json([
                    'message' => "Le document n'existe pas : $piece"
                ], 404);
            }

            // Update document status
            $document->status_id = 3;
            $document->save();

            foreach ($document->receptions as $reception) {

                $article = ArticleStock::where('code', $reception->article_code)
                    ->first();

                if (!$article) {
                    throw new \Exception("Article non trouvé avec le code: {$reception->article_code}");
                }

                $emplacement = Emplacement::where('code', $reception->emplacement_code)
                    ->first();

                if (!$emplacement) {
                    throw new \Exception("Emplacement non trouvé avec le code: {$reception->emplacement_code}");
                }

                $user = User::where('name', $reception->username)
                    ->first();

                if (!$user) {
                    throw new \Exception("Utilisateur non trouvé: {$reception->username}");
                }


                StockMovement::create([
                    'code_article'     => $article->code,
                    'designation'      => $article->description ?? 'N/A',
                    'emplacement_id'   => $emplacement->id,
                    'movement_type'    => 'IN',
                    'article_stock_id' => $article->id,
                    'quantity'         => $reception->quantity,
                    'moved_by'         => $user->id,
                    'company_id'       => $reception->company,
                    'movement_date'    => now(),
                ]);

                // ✅ Pass the model objects
                $this->stockService->stockInsert(
                    $emplacement,                       // Emplacement object
                    $article,                           // ArticleStock object
                    $reception->quantity,
                    $reception->colis_quantity ?? 0,
                    $reception->colis_type ?? 'Piece',
                    $reception->quantity
                );
            }

            DB::connection($request->company_db)->commit();

            return response()->json([
                'message' => 'Validation effectuée avec succès'
            ], 200);
        } catch (\Throwable $e) {
            DB::connection($request->company_db)->rollBack();

            \Log::error('Validation error', [
                'piece' => $piece,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return response()->json([
                'message' => 'Erreur lors de la validation',
                'error'   => $e->getMessage()
            ], 500);
        }
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
                'company'          => 'required|numeric',
                'piece'            => 'required'
            ]);

            $condition =  $request->condition ? intval($request->condition) : 1;


            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors()->first(),
                    'errors'  => $validator->errors()
                ], 422);
            }

            $article     = ArticleStock::where('code', $request->code_article)->first();
            $emplacement = Emplacement::where("code", $request->emplacement_code)->first();
            $document = Document::on($request->company_db)->where('piece', $request->piece)->first();



            if (!$article) {
                return response()->json([
                    'message' => 'Article non trouvé'
                ], 404);
            }

            if (!$document) {
                return response()->json([
                    'message' => 'Document non trouvé'
                ], 404);
            }

            if (!$emplacement) {
                return response()->json([
                    'message' => 'Emplacement non trouvé'
                ], 404);
            }


            $docentete = Docentete::on($request->company_db)
                ->lockForUpdate()
                ->findOrFail($piece);


            $docligne = $docentete->doclignes()
                ->where('AR_Ref', $request->code_article)
                ->whereRaw('CAST(DL_Qte AS INT) > CAST(DL_QteBL AS INT)')
                ->lockForUpdate()
                ->first();

            if (!$docligne || $docligne->DL_Qte < ($request->quantity + $docligne->DL_QteBL)) {
                return response()->json(['message' => "Ligne document introuvable ou Quantité insuffisante"], 422);
            }


            $emplacement = Emplacement::lockForUpdate()
                ->where('code', $request->emplacement_code)
                ->first();

            if (!$emplacement) {
                throw new HttpException(404, 'Emplacement non trouvé');
            }

            DB::connection($request->company_db)->transaction(function () use ($request, $condition, $docligne, $docentete, $emplacement) {

                $document = Document::on($request->company_db)
                    ->where('piece', $request->piece)
                    ->lockForUpdate()
                    ->first();

                if (!$document) {
                    throw new HttpException(404, 'Document non trouvé');
                }



                $user = auth()->user();
                if (!$user) {
                    return response()->json(['error' => 'User not authenticated'], 401);
                }

                DocumentReception::on($request->company_db)->create([
                    'article_code'     => $request->code_article,
                    'emplacement_code' => $request->emplacement_code,
                    'quantity'         => floatval($request->quantity) * floatval($condition),
                    'document_id'      => $document->id,
                    'username'         => $user->name,
                    'company'          => (int) $request->company,
                    'colis_type'       => $request->type_colis,
                    'colis_quantity'   => $request->condition,
                    'description'      => $docligne?->DL_Design,
                    'container_code'   => $request->container_code,
                    'depot_code' => $emplacement?->depot?->code,
                    'created_at' => now()
                ]);

                $docligne->update([
                    'DL_QteBL' => $docligne->DL_QteBL + (float) (floatval($request->quantity) * floatval($condition))
                ]);

                if ($docentete->doclignes()->sum('DL_QteBL') === $docentete->doclignes()->sum('DL_Qte')) {
                    $docentete->document()->update([
                        'status_id' => 2
                    ]);
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
