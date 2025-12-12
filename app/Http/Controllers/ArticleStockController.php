<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Imports\ArticleStockImport;
use App\Models\ArticleStock;
use App\Models\Docligne;
use App\Models\Emplacement;
use Illuminate\Http\Request;

use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ArticleStockController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = ArticleStock::query();

        // Apply filters
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $company = null;
        if ($request->has('company') && $request->company !== '') {
            $company = $request->company;
        }

        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%$search%")
                    ->orWhere('name', 'like', "%$search%")
                    ->orWhere('description', 'like', "%$search%")
                    ->orWhere('color', 'like', "%$search%");
            });
        }

        $articles = $query->paginate(100);


        // Map stock quantity for each article
        $articles->getCollection()->transform(function ($article) use($company) {
            $article->stock_prepare = $this->calculateStockPreparation($article->code);
            $article->stock_prepartion = $this->calculateZoonPrepartion($article->code);
            $article->stock = $this->calculateStock($article->code, $company);

            return $article;
        });

        return response()->json($articles);
    }


    public function calculateStock($ref_article, $company_id = null)
    {
        $article = ArticleStock::where('code', $ref_article)->first();

        if (! $article) {
            return 0;
        }

        $query = $article->palettes()->where('type', 'Stock');

        if ($company_id) {
            $query->where('company_id', $company_id);
        }

        return $query->sum('article_palette.quantity');
    }




    public function calculateStockPreparation($ref_article)
    {

        return Docligne::where('AR_Ref', $ref_article)
            ->whereIn('DO_Type', [2, 3, 1])
            ->whereColumn("DL_Qte", '>', 'DL_QteBL')
            ->sum('DL_Qte');
    }

    public function calculateZoonPrepartion($ref_article)
    {

        return Docligne::where('AR_Ref', $ref_article)
            ->whereIn('DO_Type', [2, 3, 1])
            ->sum('DL_QteBL');
    }




    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        try {
            ini_set('max_execution_time', 7200); // 2 hours
            ini_set('memory_limit', '4G'); // 1GB memory

            Excel::import(new ArticleStockImport, $request->file('file'));

            return response()->json([
                'message' => "Fichier importé avec succès"
            ], 200);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            return response()->json([
                'message' => 'Erreur de validation',
                'errors' => $e->failures()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Import failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Erreur lors de l\'importation du fichier'
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        if(!auth()->user()->hasRole('admin')){
            return response()->json(['message' => "Vous n'êtes pas authentifié ⚠️"], 402);
        }
        
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'name' => 'string|max:150',
            'color' => 'nullable|string|max:50',
            'quantity' => 'nullable|numeric',
            'stock_min' => 'nullable|numeric',
            'price' => 'nullable|numeric',
            'thickness' => 'nullable|numeric',
            'height' => 'nullable|numeric',
            'width' => 'nullable|numeric',
            'depth' => 'nullable|numeric',
            'chant' => 'nullable|string|max:100',
            'condition' => 'nullable|string|max:100',
            'code_supplier' => 'nullable|string|max:100',
            'qr_code' => 'nullable|string|max:255',
            'palette_condition' => 'nullable|string|max:100',
            'unit' => 'nullable|string|max:20',
            'gamme' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        // Update article fields
        $article_stock = ArticleStock::create($request->only([
            'code',
            'description',
            'name',
            'color',
            'qte_inter',
            'qte_serie',
            'quantity',
            'stock_min',
            'price',
            'thickness',
            'height',
            'width',
            'depth',
            'chant',
            'condition',
            'code_supplier',
            'qr_code',
            'palette_condition',
            'unit',
            'gamme',
            'category'
        ]));

        return response()->json([
            'message' => 'Article créé avec succès.',
            'id' => $article_stock->code
        ], 200);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(ArticleStock $article_stock)
    {
        return $article_stock;
        // return response()->json(['data' => ]);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    public function update(Request $request, ArticleStock $article_stock)
    {
        if(!auth()->user()->hasRole('admin')){
            return response()->json(['message' => "Vous n'êtes pas authentifié ⚠️"], 402);
        }
        


        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'name' => 'string|max:150',
            'color' => 'nullable|string|max:50',
            'qte_inter' => 'nullable|numeric',
            'qte_serie' => 'nullable|numeric',
            'quantity' => 'nullable|numeric',
            'stock_min' => 'nullable|numeric',
            'price' => 'nullable|numeric',
            'thickness' => 'nullable|numeric',
            'height' => 'nullable|numeric',
            'width' => 'nullable|numeric',
            'depth' => 'nullable|numeric',
            'chant' => 'nullable|string|max:100',
            'condition' => 'nullable|string|max:100',
            'code_supplier' => 'nullable|string|max:100',
            'qr_code' => 'nullable|string|max:255',
            'palette_condition' => 'nullable|string|max:100',
            'unit' => 'nullable|string|max:20',
            'gamme' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }


        if (!$article_stock) {
            return response()->json(['message' => 'Article introuvable.'], 404);
        }

        // Update article fields
        $article_stock->update($request->only([
            'code',
            'description',
            'name',
            'color',
            'qte_inter',
            'qte_serie',
            'quantity',
            'stock_min',
            'price',
            'thickness',
            'height',
            'width',
            'depth',
            'chant',
            'condition',
            'code_supplier',
            'qr_code',
            'palette_condition',
            'unit',
            'gamme',
            'category'
        ]));

        return response()->json([
            'message' => 'Article updated successfully.',
            'data' => $article_stock
        ], 200);
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $article = ArticleStock::findOrFail($id);
        $article->delete();

        return response()->json(['message' => 'Article deleted successfully']);
    }


    public function emplacements($code)
    {
        $article = ArticleStock::with('companies')->where('code', $code)
            ->orWhere('code_supplier', $code)
            ->orWhere('code_supplier_2', $code)
            ->orWhere('qr_code', $code)
            ->first();

        if (!$article) {
            return response()->json(['message' => 'Article introuvable.'], 404);
        }

       
        $emplacements = Emplacement::whereHas('palettes.articles', function ($query) use ($article) {
            $query->where('article_stocks.id', $article->id);
        })
            ->with([
                'depot.company',
                'palettes' => function ($q) use ($article) {
                    $q->whereHas('articles', fn($a) => $a->where('article_stocks.id', $article->id))
                        ->where('type', 'Stock')
                        ->with([
                            'articles' => fn($a) => $a->where('article_stocks.id', $article->id)
                        ]);
                }
            ])
            ->get();

        return response()->json($emplacements);
    }



    public function decrementQuantity($code, $quantity)
    {
        // 1) Find Article
        $article = ArticleStock::with('companies')->where('code', $code)
            ->orWhere('code_supplier', $code)
            ->orWhere('code_supplier_2', $code)
            ->orWhere('qr_code', $code)
            ->first();

        if (!$article) {
            return response()->json(['message' => 'Article introuvable.'], 404);
        }

        // 2) Get the FIRST emplacement where article exists (ONLY ONE!)
        $emplacement = Emplacement::whereHas('palettes.articles', function ($query) use ($article) {
            $query->where('article_stocks.id', $article->id)->where("type", 'Stock');
        })
            ->with([
                'depot.company',
                'palettes' => function ($q) use ($article) {
                    $q->whereHas('articles', fn($a) => $a->where('article_stocks.id', $article->id))
                        ->with(['articles' => fn($a) => $a->where('article_stocks.id', $article->id)]);
                }
            ])
            ->orderBy('id', 'ASC')
            ->first();

        if (!$emplacement) {
            return response()->json(['message' => 'Aucun emplacement trouvé'], 404);
        }

        // 3) Find article in the palette (pivot row which contains quantity)
        $palette = $emplacement->palettes->first();
        $paletteArticle = $palette->articles()->where('article_stocks.id', $article->id)->first();

        if (!$paletteArticle) {
            return response()->json(['message' => 'Article non trouvé dans palette'], 404);
        }

        // 4) Decrement quantity
        $currentQty = $paletteArticle->pivot->quantity;

        if ($quantity > $currentQty) {
            return response()->json(['message' => "Stock insuffisant. Disponible: $currentQty"], 400);
        }

        $palette->articles()->updateExistingPivot($article->id, [
            'quantity' => $currentQty - $quantity
        ]);

        return response()->json([
            'message' => 'Quantité mise à jour avec succès',
            'emplacement' => $emplacement,
            'quantity_before' => $currentQty,
            'quantity_after' => $currentQty - $quantity
        ]);
    }

    
}
