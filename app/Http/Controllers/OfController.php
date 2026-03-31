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

        $machine = Machine::where('ref_machine', $request->reference_machine)->first();

        try {
            $of = Of::create([
                'reference'         => $this->generateReference($machine->machine_id),
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
        $query = Of::with(['lines.article', 'user'])->latest();

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
        $year    = now()->year;
        $last    = Of::whereYear('created_at', $year)->lockForUpdate()->count();
        $counter = str_pad($last + 1, 4, '0', STR_PAD_LEFT);
        return "OF-{$machine_id}-{$counter}";
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
}
