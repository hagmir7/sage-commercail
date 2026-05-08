<?php

namespace App\Http\Controllers;

use App\Models\Machine;
use App\Models\Of;
use App\Models\OfLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OfController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'date_lancement'    => 'required|date',
            'date_demarrage'    => 'date',
            'reference_machine' => 'nullable|string|max:100',
            'type_commande'     => 'required|in:standard,speciale',
            'articles'          => 'required|array|min:1',
            'articles.*.id'     => 'required|integer',
            'articles.*.code'   => 'required|string',
            'articles.*.qte'    => 'required|numeric',
        ]);

        DB::beginTransaction();


        try {
            $of = Of::create([
                'reference'         => $this->generateReference($request->reference_machine),
                'date_lancement'    => $request->date_lancement,
                'date_demarrage'    => $request->date_demarrage,
                'reference_machine' => $request->reference_machine,
                'type_commande'     => $request->type_commande,
                'statut'            => 'brouillon',
                'user_id'        => auth()->id(),
            ]);

            foreach ($request->articles as $line) {
                OfLine::create([
                    'of_id'        => $of->id,
                    'article_stock_id'   => $line['id'],
                    'article_code' => $line['code'],
                    'quantity'          => $line['qte'],
                    'quantity_produite' => 0,
                    'statut'       => 'en_attente',
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'OF créé avec succès',
                'of'      => $of->load('lines'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage(), 'error' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request)
    {
        $query = Of::with([
            'lines'         => fn($q) => $q->orderBy('position'),
            'lines.article',
            'user'
        ])->latest();

        // Search by reference
        if ($request->filled('reference')) {
            $query->where('reference', 'like', '%' . $request->reference . '%');
        }

        // Filter by statut


        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        // Filter by date range (date_lancement)
        if ($request->filled('date_debut') && $request->filled('date_fin')) {
            $query->whereBetween('date_lancement', [
                $request->date_debut,
                $request->date_fin,
            ]);
        }

        return response()->json($query->paginate(20));
    }


    public function show($id)
    {
        $of = Of::with(['lines'])->findOrFail($id);
        return response()->json($of);
    }

    private function generateReference($machine_id): string
    {
        $lastOf = Of::where('reference_machine', $machine_id)
            ->where('reference', 'like', "OF-{$machine_id}-%")
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();

        $next = 1;

        if ($lastOf && preg_match('/(\d+)$/', $lastOf->reference, $matches)) {
            $next = (int) $matches[1] + 1;
        }

        return 'OF-' . $machine_id . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
    }


    public function update(Request $request, $of_id)
    {
        $request->validate([
            'statut' => 'sometimes|in:brouillon,lancé,en_cours,terminé,annulé',
        ]);

        $of = Of::find($of_id);
        $of->update([
            'statut' => $request->statut
        ]);

        return response()->json(['message' => 'OF mis à jour', 'of' => $of]);
    }


    public function duplicate(Request $request, $id)
    {
        $request->validate([
            'reference_machine'     => 'nullable|string',
            'date_lancement_prevue' => 'required|date_format:Y-m-d',
            'date_demarrage'        => 'nullable|date_format:Y-m-d',
        ]);

        $original = Of::with('lines')->findOrFail($id);

        $referenceMachine = $request->input('reference_machine', $original->reference_machine);

        $newOf = $original->replicate();
        $newOf->reference_machine     = $referenceMachine;
        $newOf->reference             = $this->generateReference($referenceMachine);
        $newOf->statut                = 'brouillon';
        $newOf->date_lancement = $request->input('date_lancement_prevue');
        $newOf->date_demarrage        = $request->input('date_demarrage');
        $newOf->save();

        foreach ($original->lines as $line) {
            $newLine = $line->replicate();
            $newLine->of_id = $newOf->id;
            $newLine->save();
        }

        return response()->json(['message' => 'OF dupliqué avec succès'], 201);
    }

    public function destroy($id)
    {
        $of = Of::findOrFail($id);
        $of->lines()->delete();
        $of->delete();

        return ['message' => "OF Supprimé avec succès"];
    }


    public function destroyOfLine($of_line_id)
    {
        $line = OfLine::findOrFail($of_line_id);
        $line->delete();

        return ['message' => "Article Supprimé avec succès"];
    }

    public function updateOfLine(Request $request, $of_line_id)
    {
        $line = OfLine::findOrFail($of_line_id);

        $request->validate([
            'quantity' => 'required|numeric|min:0',
        ]);

        $line->update([
            'quantity' => $request->quantity,
        ]);

        return ['message' => "Quantité mise à jour avec succès"];
    }

    public function reorder(Request $request, Of $of)
    {
        $request->validate([
            'lines'          => 'required|array',
            'lines.*.id'     => 'required|integer|exists:of_lines,id',
            'lines.*.position' => 'required|integer|min:1',
        ]);

        foreach ($request->lines as $item) {
            OfLine::where('id', $item['id'])
                ->where('of_id', $of->id)  // security: ensure line belongs to this OF
                ->update(['position' => $item['position']]);
        }

        return response()->json(['message' => 'Ordre mis à jour avec succès']);
    }


    public function addOfLine(Request $request, Of $of)
    {
        $request->validate([
            'articles'            => 'required|array|min:1',
            'articles.*.code'     => 'required|string|exists:article_stocks,code',
            'articles.*.quantity' => 'nullable|numeric|min:0.01',
        ]);

        $existingCodes = $of->lines()
            ->where('article_code', '!=', 'SP000001') // ignore SP00000 in duplicate check
            ->pluck('article_code')
            ->toArray();

        $newArticles = collect($request->articles)
            ->filter(function ($a) use ($existingCodes) {
                // allow duplicates only for SP00000
                if ($a['code'] === 'SP000001') {
                    return true;
                }

                return !in_array($a['code'], $existingCodes);
            })
            ->values();

        if ($newArticles->isEmpty()) {
            return response()->json([
                'message' => 'Tous les articles sélectionnés existent déjà dans cet OF',
                'data'    => [],
            ], 422);
        }

        $nextPosition = ($of->lines()->max('position') ?? 0) + 1;

        $lines = $newArticles->map(function ($article, $i) use ($of, $nextPosition) {
            $articleStock = \App\Models\ArticleStock::where('code', $article['code'])->firstOrFail();

            return $of->lines()->create([
                'article_stock_id' => $articleStock->id,
                'article_code'     => $article['code'],
                'quantity'         => $article['quantity'] ?? 1,
                'position'         => $nextPosition + $i,
            ]);
        })->each->load('article');

        $skipped = count($request->articles) - $newArticles->count();

        $msg = "{$lines->count()} article(s) ajouté(s) avec succès";

        if ($skipped > 0) {
            $msg .= ", {$skipped} ignoré(s) (déjà présent(s))";
        }

        return response()->json([
            'message' => $msg,
            'data'    => $lines,
        ], 201);
    }
}
