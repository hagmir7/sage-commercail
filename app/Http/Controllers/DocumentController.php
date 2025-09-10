<?php

namespace App\Http\Controllers;

use App\Models\Docentete;
use App\Models\Docligne;
use App\Models\Document;
use Carbon\Carbon;
use Illuminate\Http\Request;
use DateTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DocumentController extends Controller
{
    public function checkControlled($piece)
    {
        $document = Document::with('palettes')->where('piece', $piece)->first();
        if (!$document) {
            return response()->json(['error' => "Document not found!"], 404);
        }

        $palettes = $document->palettes;

        $controlled = $palettes->every(function ($docPalette) {
            return !$docPalette->lines()->wherePivotNull('controlled_at')->exists();
        });

        return $controlled;
    }

    public function progress($piece)
    {
        $document = Document::with(['docentete.doclignes', 'lines.palettes'])->where("piece", $piece)->first();

        if (!$document) {
            return response()->json(["error" => "Document not found"], 404);
        }

        $lines = $document->lines
            ->where('ref', '!=', 'SP000001')
            ->whereNotIn('design', ['Special', '', 'special']);

        $required_qte = $lines->sum("quantity") ?? 0;

        $current_qte = 0;
        foreach ($lines as $line) {
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



    public function longList()
    {
        $documents = Document::with(['status', 'lines.palettes'])
            ->withCount('lines')
            ->orderByDesc('updated_at')
            ->get();

        $documents = $documents->map(function ($document) {

            $lines = $document->lines
                ->where('ref', '!=', 'SP000001')
                ->whereNotIn('design', ['Special', '', 'special']);


            $required_qte = $lines->sum('quantity') ?? 0;



            $current_qte = 0;
            foreach ($lines as $line) {
                foreach ($line->palettes as $palette) {
                    $current_qte += $palette->pivot->quantity;
                }
            }

            $progress = $required_qte > 0 ? round(($current_qte / $required_qte) * 100, 2) : 0;

            $document->current_qte = $current_qte;
            $document->required_qte = $required_qte;
            $document->progress = intval($progress);

            return $document;
        });

        return response()->json($documents);
    }

    public function list(Request $request)
    {
        $documents = Document::with(['status', 'lines.palettes'])
            ->whereHas('docentete')
            ->withCount('lines')
            ->orderByDesc('updated_at');


        // Searche
        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $documents->where(function ($q) use ($search) {
                $q->where('piece', 'like', "%$search%")
                    ->orWhere('ref', 'like', "%$search%")
                    ->orWhere('client_id', 'like', "%$search%");
            });
        }

        // Date filtter
        if ($request->dates) {
            $date_array = explode(',', $request->dates);
            $start_date = DateTime::createFromFormat('d/m/Y', trim($date_array[0]))->format('Y-m-d');
            $end_date = DateTime::createFromFormat('d/m/Y', trim($date_array[1]))->format('Y-m-d');
            $documents->whereBetween('created_at', [$start_date, $end_date]);
        }


        $documents = $documents->get()->map(function ($document) {

            $lines = $document->lines
                ->where('ref', '!=', 'SP000001')
                ->whereNotIn('design', ['Special', '', 'special']);

            $required_qte = $lines->sum(function ($line) {
                return floatval($line->quantity);
            });

            $current_qte = 0;
            $companies = [];

            foreach ($lines as $line) {
                if (!in_array($line->company_id, $companies)) {
                    $companies[] = $line->company_id;
                }
                foreach ($line->palettes as $palette) {
                    $current_qte += floatval($palette->pivot->quantity);
                }
            }

            $progress = $required_qte > 0 ? round(($current_qte / $required_qte) * 100, 2) : 0;


            $companyDisplay = '';
            if (count($companies) > 1) {
                $companyDisplay = 'Inter & Serie';
            } elseif (count($companies) === 1) {
                $company = \App\Models\Company::find($companies[0]);
                $companyDisplay = $company ? $company->name : 'Unknown Company';
            }

            return [
                'id' => $document->id,
                'name' => $document->name,
                'piece' => $document->piece,
                'ref' => $document->ref,
                'expedition' => $document->expedition,
                'company' => $companyDisplay,
                'client' => $document->client_id,
                'status' => [
                    'id' => $document->status->id ?? null,
                    'name' => $document->status->name ?? null,
                    'color' => $document->status->color ?? null,
                ],
                'lines_count' => $document->lines_count,
                'current_qte' => $current_qte,
                'required_qte' => $required_qte,
                'progress' => intval($progress),
                'updated_at' => $document->updated_at,
                'created_at' => $document->created_at,
            ];
        });
        return response()->json($documents);
    }


    // Document (PL) a livree
    public function ready()
    {
        $documents = Document::whereHas('docentete', function ($query) {
            $query->where('DO_Statut', 2)
                ->where('DO_Domaine', 0)
                ->where('DO_Type', 2);
        })
            ->with([
                'docentete' => function ($query) {
                    $query->select('cbMarq', 'DO_Piece', 'DO_Type', 'DO_DateLivr', 'DO_Statut', 'DO_TotalHTNet', 'DO_TotalTTC');
                },
                'status'
            ])
            ->select('id', 'docentete_id', 'piece', 'type', 'ref', 'expedition', 'client_id', 'status_id')
            ->withCount('lines')
            ->get();

        return response()->json($documents);
    }


    // Hesoty
    public function history($piece): string
    {
        $docligne = Docligne::whereIn('DO_Type', [3, 5])->where('DO_Piece', $piece)
            ->orWhere('DL_PieceBC', $piece)
            ->orWhere('DL_PieceBL', $piece)
            ->orWhere('DL_PiecePL', $piece)
            ->orWhere('DL_PieceDE', $piece)
            ->first();

        return strval($docligne->docentete->cbMarq);
    }


    public function preparationList(Request $request)
    {
        $user_roles = auth()->user()->roles()->pluck('name', 'id');

        $query = Document::with([
            'companies',
            'docentete:cbMarq,DO_Date,DO_DateLivr,DO_Reliquat'
        ])
            ->whereHas('lines', function ($query) use ($user_roles) {
                $query->where('company_id', auth()->user()->company_id);

                $common = array_intersect($user_roles->toArray(), ['fabrication', 'montage', 'preparation_cuisine', 'preparation_trailer', 'magasinier']);
                if (!empty($common)) {
                    $query->whereIn("role_id", $user_roles->keys());
                }
            })
            ->whereHas('companies', function ($query) {
                $query->whereIn('document_companies.status_id', [1, 2, 3, 4, 5, 6, 7]);
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


    // Controller & Validation List
    public function validationControllerList(Request $request)
    {
        $query = Document::with([
            'companies',
            'docentete:cbMarq,DO_Date,DO_DateLivr,DO_Reliquat',
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


        
        public function documentToConvert($piece)
        {
            return Docligne::where(function ($query) use ($piece) {
                $query->whereIn('DO_Type', [3, 5]) // BL or FA
                    ->where('DO_Piece', $piece);
            })
            ->orWhere('DL_PieceBC', $piece)
            ->orWhere('DL_PieceBL', $piece)
            ->orWhere('DL_PiecePL', $piece)
            ->orWhere('DL_PieceDE', $piece)
            ->first();
        }


        public function convertDocument(Document $document)
        {
            // Find latest BL linked to this PL
            $doc = Docentete::where('DO_Type', 3) // BL type
                ->where('DO_Ref', $document->piece) // PL reference
                ->orderByDesc('DO_Date')
                ->first();

            if (!$doc) {
                return $document; // nothing found
            }

            $document->update([
                'docentete_id' => $doc->cbMarq,
                'status_id'    => str_contains($doc->DO_Piece, 'BL') ? 12 : $document->status_id,
                'piece_bl'     => str_contains($doc->DO_Piece, 'BL') ? $doc->DO_Piece : $document->piece_bl,
                'piece_fa'     => str_contains($doc->DO_Piece, 'FA') ? $doc->DO_Piece : $document->piece_fa,
            ]);

            return $document->fresh();
        }


        public function livraison(Request $request)
        {
            // Auto-convert orphan documents if no search
            if (!$request->filled('search')) {
                $documents = Document::whereDoesntHave('docentete')->get();
                if ($documents->count()) {
                    $documents->each(fn ($doc) => $this->convertDocument($doc));
                }
            }

            $query = Docentete::with([
                'document' => function ($q) {
                    $q->with(['status', 'companies'])
                    ->withCount('palettes');
                },
            ])
            ->select(
                'DO_Domaine',
                'DO_Type',
                'DO_Piece',
                'DO_Date',
                'DO_Ref',
                'DO_Tiers',
                'DO_Statut',
                'cbMarq',
                'cbCreation',
                'DO_DateLivr',
                'DO_Expedit'
            )
            ->where('DO_Type', 3) // BL only
            ->whereBetween('DO_Date', [
                Carbon::today()->subDays(40),
                Carbon::today()
            ]);

            // Restrict by company if controleur
            if (auth()->user()->hasRole("controleur")) {
                $query->whereHas('document.companies', function ($q) {
                    $q->where('companies.id', auth()->user()->company_id);
                });
            }

            // Apply search
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('DO_Piece', 'like', "%$search%")
                    ->orWhere('DO_Tiers', 'like', "%$search%")
                    ->orWhere('DO_Ref', 'like', "%$search%");
                });
            }

            $docentetes = $query->orderByDesc('DO_Date')->get();

            return response()->json($docentetes);
        }




    public function show(Document $document)
    {
        $document->load([
            // Eager load lines with join + ordering
            'lines' => fn($query) =>
            $query->join('F_DOCLIGNE', 'lines.docligne_id', '=', 'F_DOCLIGNE.cbMarq')
                ->orderBy('F_DOCLIGNE.DL_Ligne')
                ->select('lines.*'),

            'lines.status',
            'lines.role',
            'lines.company',
            'lines.article_stock',
            'lines.palettes',


            'lines.docligne:DO_Domaine,DO_Type,CT_Num,DO_Piece,DL_Ligne,DL_Design,DO_Ref,DL_PieceDE,DL_PieceBC,DL_PiecePL,DL_PieceBL,DL_Qte,AR_Ref,cbMarq,Nom,Hauteur,Largeur,Profondeur,Langeur,Couleur,Chant,Episseur,Description,Poignée,Rotation',
        ]);


        $required_qte = $document->lines
            ->where('ref', '!=', 'SP000001')
            ->whereNotIn('design', ['Special', '', 'special'])->sum("quantity") ?? 0;

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
            'progress' => intval($progress),
            'document' => $document
        ]);
    }










    public function addChargement(Document $document, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        DB::transaction(function () use ($document, $request) {

            foreach ($document->palettes()->where('company_id', auth()->user()?->company_id)->get() as $palette) {
                $palette->delivered_by = $request->user_id;
                $palette->save();
            }

            $document->companies()->updateExistingPivot(auth()->user()->company_id, [
                'status_id' => 13,
            ]);



            // DB::table('document_companies')
            //     ->where('document_id', $document->id)
            //     ->where('company_id', auth()->id())
            //     ->update(['status_id' => 13]);

            $document->update(['status_id' => 13]);
        });

        $document->load(['palettes', 'companies']);

        return response()->json([
            'message' => 'Agent de chargement attribué avec succès',
            'document' => $document
        ]);
    }


    public function deliveredPalettes($piece)
    {
        $document = Document::withCount('palettes')->with(['palettes' => function ($query) {
            $query->whereNotNull('delivered_at');
        }])->where('piece', $piece)->first();

        if (!$document) {
            return response()->json([
                'error' => 'Document not found',
                'message' => 'Document non trouvée'
            ], 404);
        }

        return $document;
    }

    public function palettes($piece)
    {
        $document = Document::with(['palettes'])->where('piece', $piece)->first();

        if (!$document) {
            return response()->json([
                'error' => 'Document not found',
                'message' => 'Document non trouvée'
            ], 404);
        }
    }
}
