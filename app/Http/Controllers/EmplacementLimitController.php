<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Imports\EmplacementLimitImport;
use App\Models\ArticleStock;
use App\Models\Emplacement;
use App\Models\EmplacementLimit;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;
use Illuminate\Support\Facades\Validator;

class EmplacementLimitController extends Controller
{

    public function index(Request $request)
    {
        $query = EmplacementLimit::with(['emplacement', 'article']);

        // Search across article and emplacement fields
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('article', function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                })
                    ->orWhereHas('emplacement', function ($q) use ($search) {
                        $q->where('code', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by emplacement_id
        if ($request->filled('emplacement_id')) {
            $query->where('emplacement_id', $request->emplacement_id);
        }

        // Filter by article_id
        if ($request->filled('article_id')) {
            $query->where('article_id', $request->article_id);
        }

        // Filter by min stock limit
        if ($request->filled('min_limit')) {
            $query->where('quantity', '>=', $request->min_limit);
        }

        // Filter by max stock limit
        if ($request->filled('max_limit')) {
            $query->where('quantity', '<=', $request->max_limit);
        }

        // Sorting
        $sortBy    = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $allowedSorts = ['id', 'quantity', 'created_at', 'updated_at'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        $perPage = in_array($request->get('per_page'), [25, 50, 100, 200])
            ? $request->get('per_page')
            : 100;

        return $query->paginate($perPage)->appends($request->query());
    }



    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => ['required', 'numeric', 'min:0'],
            'emplacement_code' => ['required', 'exists:emplacements,code'],
            'article_code' => ['required', 'exists:article_stocks,code'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        $empalcement = Emplacement::where('code', $data['emplacement_code'])->first();
        $article = ArticleStock::where("code", $data['article_code'])->first();




        $emplacementLimit = EmplacementLimit::updateOrCreate(
            [
                'emplacement_id' => $empalcement->id,
                'article_stock_id' => $article->id,
            ],
            [
                'quantity' => $data['quantity'],
            ]
        );

        return response()->json([
            'message' => 'Saved successfully (created or updated)',
            'data' => $emplacementLimit
        ], 200);
    }



    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|extensions:xlsx,xls,csv'
        ]);

        try {
            Excel::import(new EmplacementLimitImport, $request->file('file'));

            return response()->json([
                'message' => 'Import terminé avec succès'
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'Erreur lors de l\'import : ' . $e->getMessage()
            ], 500);
        }
    }
}
