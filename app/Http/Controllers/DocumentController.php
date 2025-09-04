<?php

namespace App\Http\Controllers;

use App\Models\Docentete;
use App\Models\Docligne;
use App\Models\Document;
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


    /**
     * Find the corresponding Docligne entry to convert from a given piece.
     */
    public function documentToConvert($piece)
    {
        return Docligne::where(function ($query) use ($piece) {
            $query->whereIn('DO_Type', [3, 5])
                ->where('DO_Piece', $piece);
        })
            ->orWhere('DL_PieceBC', $piece)
            ->orWhere('DL_PieceBL', $piece)
            ->orWhere('DL_PiecePL', $piece)
            ->orWhere('DL_PieceDE', $piece)
            ->first();
    }

    /**
     * Convert documents by linking them to their corresponding docentete based on related Docligne entries.
     */
    public function convertDocument()
    {
        Document::doesntHave('docentete')
            ->get()
            ->each(function ($document) {
                $doc = $this->documentToConvert($document->piece);
                $cbMarq = $doc?->docentete?->cbMarq;
                $doPiece = $doc?->docentete?->DO_Piece;
                $document->update([
                    'docentete_id' => $cbMarq ?? $document->docentete_id,
                    'status_id' => str_contains($doPiece, 'BL') ? 12 : $document->status_id,
                    'piece_bl'     => str_contains($doPiece, 'BL') ? $doPiece : $document->piece_bl,
                    'piece_fa'     => str_contains($doPiece, 'FA') ? $doPiece : $document->piece_fa,
                ]);
            });
    }


    public function readyDocuments()
    {
        $this->convertDocument();

        return Document::with('docentete')
            ->whereNull('piece_bl')
            ->whereIn('status_id', [10, 11])
            ->get();
    }



    /**
     * Entry point to convert documents and return updated list.
     */
    public function livraison(Request $request)
    {
        $this->convertDocument();

        $user = auth()->user();
        $documents = Document::with([
            'docentete:DO_Type,DO_Piece,DO_Date,DO_DateLivr,cbMarq,DO_Statut',
            'status',
            'companies'
        ])->withCount('palettes');

        if ($user->hasRole('commercial')) {
            $documents->whereIn('status_id', [11, 12, 13, 14])
                ->whereNull('piece_fa');
        } elseif ($user->hasRole('chargement')) {
            $documents->where([
                ['status_id', '=', 13],
            ])->whereHas('palettes', function ($query) {
                $query->where("delivered_by", auth()->id());
            });
        } elseif ($user->hasRole('preparation')) {
            $status = $request->input('status');
            $documents->when(
                $status,
                fn($query) => $query->where('status_id', $status),
                fn($query) => $query->whereIn('status_id', [11, 12, 13])
            );
        }

        if ($request->has('search') && $request->search != '') {
            $search = $request->search;
            $documents->where(function ($query) use ($search) {
                $query->where('piece', 'like', "%$search%")
                    ->orWhere('ref', 'like', "%$search%")
                    ->orWhere('client_id', 'like', "%$search%")
                    ->orWhere('piece_bl', "%$search%");
            });
        }

        return $documents->orderByDesc('created_at')->paginate(20);
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
