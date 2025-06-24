<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ArticleStock;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
        if ($request->has('family_id')) {
            $query->where('family_id', $request->family_id);
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


        $articles = $query->with(['family'])->paginate(100);
        return response()->json($articles);
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
            'family_id' => 'required|exists:article_families,id',
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
            'name' => 'required|string|max:150',
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
            'family_id' => 'nullable|exists:F_FAMILLE,cbMarq',
            'condition' => 'nullable|string|max:100',
            'code_supplier' => 'nullable|string|max:100',
            'qr_code' => 'nullable|string|max:255',
            'palette_condition' => 'nullable|string|max:100',
            'unit' => 'nullable|string|max:20',
            'gamme' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }


        if (!$article_stock) {
            return response()->json(['message' => 'Article not found.'], 404);
        }

        // Update article fields
        $article_stock->update($request->only([
            'code', 'description', 'name', 'color', 'qte_inter', 'qte_serie', 'quantity', 'stock_min',
            'price', 'thickness', 'height', 'width', 'depth', 'chant', 'family_id',
            'condition', 'code_supplier', 'qr_code', 'palette_condition', 'unit', 'gamme'
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
