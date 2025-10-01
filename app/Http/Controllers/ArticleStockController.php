<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Imports\ArticleStockImport;
use App\Models\ArticleStock;
use App\Models\Docligne;
use App\Models\Line;
use App\Models\User;
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
        $articles->getCollection()->transform(function ($article) {
            $article->stock_prepare = $this->calculateStockPrepare($article->code); 
            $article->stock_prepartion = $this->calculateZoonPrepartion($article->code); 
            $article->stock = $this->calculateStock($article->code); 

            return $article;
        });

        return response()->json($articles);
    }




    public function calculateStock($ref_article)
    {
        // Find the article by its code
        $article = ArticleStock::where('code', $ref_article)->first();

        if (! $article) {
            return 0; // if no article found
        }

        // Get all palettes with type = "Stock" and sum the pivot quantity
        $totalQuantity = $article->palettes()
            ->where("type", "Stock")
            ->sum("article_palette.quantity"); 

        return $totalQuantity;
    }





    public function calculateStockPrepare($ref_article)
    {
        return Docligne::where('AR_Ref', $ref_article)
            ->whereHas('line', function ($query) {
                $query->whereIn('status_id', [8, 9, 10]);
            })
            ->sum('DL_Qte');
    }

        public function calculateZoonPrepartion($ref_article)
    {
        return Docligne::where('AR_Ref', $ref_article)
            ->whereHas('line', function ($query) {
                $query->whereIn('status_id', [3, 4, 5, 6, 7]);
            })
            ->sum('DL_Qte');
    }



 




    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        try {
            // Set higher limits for large imports
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
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:255|unique:articles',
            'description' => 'required|string',
            'name' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:255',
            'qte_inter' => 'integer|min:0',
            'qte_serie' => 'integer|min:0',
            'palette_id' => 'nullable|exists:palettes,id',
            'thickness' => 'nullable|numeric',
            'hieght' => 'nullable|numeric',
            'width' => 'nullable|numeric',
            'depth' => 'nullable|numeric',
            'chant' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $article = ArticleStock::create($request->all());
        return response()->json(['data' => $article, 'message' => 'Article created successfully'], 201);
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
            return response()->json(['message' => 'Article not found.'], 404);
        }

        // Update article fields
        $article_stock->update($request->only([
            'code', 'description', 'name', 'color', 'qte_inter', 'qte_serie', 'quantity', 'stock_min',
            'price', 'thickness', 'height', 'width', 'depth', 'chant',
            'condition', 'code_supplier', 'qr_code', 'palette_condition', 'unit', 'gamme', 'category'
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


 
    
}
