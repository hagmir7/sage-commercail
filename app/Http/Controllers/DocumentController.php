<?php

namespace App\Http\Controllers;

use App\Models\Docentete;
use App\Models\Docligne;
use App\Models\Document;
use App\Models\Line;
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
        $document = Document::with(['docentete.doclignes', 'lines.docligne', 'lines.palettes'])
            ->where("piece", $piece)
            ->first();

        if (!$document) {
            return response()->json(["error" => "Document not found"], 404);
        }

        $lines = $document->lines
            ->where('ref', '!=', 'SP000001')
            ->whereNotIn('design', ['Special', '', 'special']);

        $required_qte = $lines->sum(fn($line) => $line->docligne?->DL_Qte ?? 0);
        $current_qte  = $lines->sum(fn($line) => $line->docligne?->DL_QteBL ?? 0);

        $progress = $required_qte > 0
            ? round(($current_qte / $required_qte) * 100, 2)
            : 0;

        return response()->json([
            'current_qte'  => $current_qte,
            'required_qte' => $required_qte,
            'progress'     => intval($progress),
        ]);
    }


    public function longList()
    {
        $documents = Document::with(['status', 'lines.palettes'])
            ->withCount('lines')
            ->orderByDesc('id')
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

            $required_qte = $lines->sum(fn($line) => $line->docligne?->DL_Qte ?? 0);
            $current_qte  = $lines->sum(fn($line) => $line->docligne?->DL_QteBL ?? 0);

            $progress = $required_qte > 0
                ? round(($current_qte / $required_qte) * 100, 2)
                : 0;



            $companies = $lines->pluck('company_id')->unique()->values()->all();



            $companies = $lines->pluck('company_id')->unique()->values();
            $companyDisplay = match ($companies->count()) {
                0       => 'Société inconnue',
                1       => optional(\App\Models\Company::find($companies->first()))->name ?? 'Société inconnue',
                default => 'Inter & Serie',
            };


            return [
                'id' => $document->id,
                'name' => $document->name,
                'piece' => $document->piece,
                'ref' => $document->ref,
                'expedition' => $document->expedition,
                'company' => $companyDisplay,
                'client' => $document->client_id,
                'piece_bl' => $document->piece_bl,
                'piece_fa' => $document->piece_fa,
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
            ->whereHas('docentete', function ($query) {
                $query->where('DO_Domaine', 0)
                    ->where('DO_Statut', 1)
                    ->where('DO_Type', 2);
            })
            ->whereHas('lines', function ($q) use ($user_roles) {
                $q->where('company_id', auth()->user()->company_id);

                $common = array_intersect(
                    $user_roles->toArray(),
                    ['fabrication', 'montage', 'preparation_cuisine', 'preparation_trailer', 'magasinier']
                );

                if (!empty($common)) {
                    $q->whereIn("role_id", $user_roles->keys());
                }
            })
            ->addSelect([
                'documents.*',
                'has_user_printer' => \DB::table('user_document_printer')
                    ->selectRaw('CASE WHEN COUNT(*) > 0 THEN 1 ELSE 0 END')
                    ->whereColumn('user_document_printer.document_id', 'documents.id')
                    ->where('user_document_printer.user_id', auth()->id())
            ])
            ->whereHas('companies', function ($q) {
                $q->whereIn('document_companies.status_id', [1, 2, 3, 4, 5, 6, 7]);
            });

        // 🔍 Search
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where(function ($q) use ($search) {
                $q->where('ref', 'like', "%{$search}%")
                    ->orWhere('piece', 'like', "%{$search}%");
            })
                ->orWhereHas('companies', function ($q) use ($search) {
                    $q->where('client_id', 'like', "%{$search}%");
                });
        }

        $documents = $query->orderByDesc('id')->paginate(20);

        return response()->json($documents);
    }

    /**
     * Show single document with progress
     */
    public function show($document_piece)
    {
        $document = Document::where('piece_fa', $document_piece)
            ->orWhere('piece_bl', $document_piece)
            ->orWhere('piece', $document_piece)
            ->first();

        if (!$document) {
            return response()->json([
                'message' => 'Document not found'
            ], 404);
        }

        $document->load([
            'lines' => fn($q) =>
            $q->join('F_DOCLIGNE', 'lines.docligne_id', '=', 'F_DOCLIGNE.cbMarq')
                ->orderBy('F_DOCLIGNE.DL_Ligne')
                ->select('lines.*'),

            'lines.status',
            'lines.role',
            'lines.company',
            'lines.article_stock',
            'lines.palettes',

            'lines.docligne:DO_Domaine,DO_Type,CT_Num,DO_Piece,DL_Ligne,DL_Design,DO_Ref,DL_PieceDE,DL_PieceBC,DL_PiecePL,DL_PieceBL,DL_Qte,AR_Ref,cbMarq,Nom,Hauteur,Largeur,Profondeur,Langeur,Couleur,Chant,Episseur,Description,Poignée,Rotation,DL_QteBL',
        ]);

        $lines = $document->lines
            ->where('ref', '!=', 'SP000001')
            ->whereNotIn('design', ['Special', '', 'special']);

        $required_qte = $lines->sum(fn($line) => $line->docligne?->DL_Qte ?? 0);
        $current_qte  = $lines->sum(fn($line) => $line->docligne?->DL_QteBL ?? 0);

        $progress = $required_qte > 0
            ? round(($current_qte / $required_qte) * 100, 2)
            : 0;

        return response()->json([
            'current_qte'  => $current_qte,
            'required_qte' => $required_qte,
            'progress'     => intval($progress),
            'document'     => $document,
        ]);
    }



    public function validationControllerList(Request $request)
    {
        $query = Document::with(['companies', 'lines', 'docentete:DO_Piece,cbMarq,DO_DateLivr']);

        if (auth()->user()->hasRole('commercial')) {
            $query = $query->whereIn('status_id', [8, 9, 10, 11]);
        } else {
            $query->whereHas('lines', function ($query) {
                $query->where('company_id', auth()->user()->company_id);
            })->whereHas('companies', function ($query) {
                $query->whereIn('document_companies.status_id', [8, 9, 10, 11]);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('ref', 'like', "%$search%")
                    ->orWhere('piece', 'like', "%$search%")
                    ->orWhere('client_id', 'like', "%$search%");
            });
        }

        $query->whereHas('docentete')->whereNull('piece_fa')->whereNull('piece_bl');

        $documents = $query->orderBy('id', 'desc')->paginate(20);
        return response()->json($documents);
    }

    public function getDocumentsBL($piece)
    {
        return Docligne::with('docentete')
            ->where("DL_PiecePL", $piece)
            ->get()
            ->unique('DO_Piece');
    }

    public function convertDocument(Document $document)
    {
        return DB::transaction(function () use ($document) {
            $docs = $this->getDocumentsBL($document->piece);

            if ($docs->isEmpty()) {
                return $document;
            }

            $doc = $docs->first()->docentete;

            if ($docs->count() > 1) {
                $doc = Docentete::whereIn("DO_Piece", $docs->pluck('DO_Piece'))
                    ->whereDoesntHave('document')
                    ->first();
            }

            if (!$doc) {
                return 0;
            }

            // Update the main document
            $document->update([
                'docentete_id' => $doc->cbMarq,
                'status_id'    => str_contains($doc->DO_Piece, 'BL') ? 12 : $document->status_id,
                'piece_bl'     => str_contains($doc->DO_Piece, 'BL') ? $doc->DO_Piece : ($docs->first()->DL_PieceBL ?? $document->piece_bl),
                'piece_fa'     => str_contains($doc->DO_Piece, 'FA') ? $doc->DO_Piece : $document->piece_fa,
            ]);

            // Update related lines
            $lines = Line::where('document_id', $document->id)->get();

            foreach ($lines as $line) {
                $docligne = optional($doc->doclignes)
                    ->where('AR_Ref', $line->ref)
                    ->first();

                if ($docligne) {
                    $line->update(['docligne_id' => $docligne->cbMarq]);
                }
            }

            return $document->fresh();
        });
    }

    public function livraison(Request $request)
    {

        if (!$request->filled('search')) {
            $documents = Document::whereDoesntHave('docentete')->whereNull('piece_bl')->orWhereNull('piece_fa')->get();

            if ($documents->isNotEmpty()) {
                $documents->each(fn($document) => $this->convertDocument($document));
            }
        }

        // 🔹 Main query for BLs
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
            ->where('DO_Type', 3)
            ->whereBetween('DO_Date', [
                Carbon::today()->subDays(40),
                Carbon::today()
            ]);

        // 🔹 Restrict for controleur role
        if (auth()->user()->hasRole("controleur")) {
            $query->whereHas('document.companies', function ($q) {
                $q->where('companies.id', auth()->user()->company_id);
            });
        }


        if (auth()->user()->hasRole("chargement")) {
            $query->whereHas("document.palettes", function ($q) {
                $q->where('delivered_by', auth()->id());
            });
        }



        $query->orderByDesc('DO_Date');

        // 🔹 Apply search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('DO_Piece', 'like', "%$search%")
                    ->orWhere('DO_Tiers', 'like', "%$search%")
                    ->orWhere('DO_Ref', 'like', "%$search%");
            });
        }

        return response()->json($query->get());
    }



    public function addChargement(Document $document, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
                'message' => $validator->errors()->first()
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
        $makeQuery = fn() => Document::withCount([
            'palettes as delivered_palettes_count' => fn($q) => $q->whereNotNull('delivered_at'),
        ])
            ->with([
                'palettes' => fn($q) => $q->whereNotNull('delivered_at'),
            ]);

        $document = $makeQuery()->where('piece_fa', $piece)->first()
            ?? $makeQuery()->where('piece_bl', $piece)->first()
            ?? $makeQuery()->where('piece', $piece)->first();

        if (!$document) {
            return response()->json([
                'error' => 'Document not found',
                'message' => "Document non trouvée {$piece}",
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


    public function print(Document $document)
    {
        $document->companies()->updateExistingPivot(auth()->user()->company_id, [
            'printed' => true,
            'updated' => false,
            'updated_at' => now(),
        ]);
        $document->printers()->syncWithoutDetaching(auth()->id());
        return response()->json(['message' => "Document imprimé avec succès"]);
    }

    public function resetPrint(Document $document)
    {
        $companyIds = $document->companies->pluck('id')->toArray();

        $document->companies()->updateExistingPivot($companyIds, [
            'printed'   => false,
            'updated'   => true,
            'updated_at' => now(),
        ]);

        return response()->json(['message' => "Réinitialisation d'impression avec succès"]);
    }


    public function archive(Request $request)
    {
        $query = Document::with(['docentete:DO_Domaine,DO_Type,DO_Piece,DO_Date,DO_Ref,DO_Tiers,DO_Statut,cbMarq,cbCreation,DO_DateLivr,DO_Expedit', 'status'])
            ->whereHas('docentete')
            ->when($request->filled('type'), function ($q) use ($request) {
                if ($request->type == 1) {
                    $q->where('piece', 'like', '%PL%');
                } elseif ($request->type == 2) {
                    $q->where(function ($q2) {
                        $q2->where('piece_bl', 'like', '%BL%')
                            ->orWhere('piece', 'like', '%BL%');
                    });
                }
            });

        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('piece_fa', 'like', "%$search%")
                    ->orWhere('piece', 'like', "%$search%")
                    ->orWhere('ref', 'like', "%$search%")
                    ->orWhere('client_id', 'like', "%$search%")
                    ->orWhere('piece_bl', 'like', "%$search%");
            });
        }

        if ($request->filled('date')) {
            $dates = explode(',', $request->date, 2);
            $start = Carbon::parse(urldecode($dates[0]))->startOfDay();
            $end = Carbon::parse(urldecode($dates[1] ?? $dates[0]))->endOfDay();

            $query->whereBetween('created_at', [$start, $end]);
        }

        $documents = $query->select('documents.*')->orderByDesc('id')->paginate(30);

        return response()->json($documents, 200, [], JSON_UNESCAPED_UNICODE);
    }
}
