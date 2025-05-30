<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ArticleStock;
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

        if ($request->has('palette_id')) {
            $query->where('palette_id', $request->palette_id);
        }

        $articles = $query->with(['family', 'palette'])->get();
        return response()->json(['data' => $articles]);
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
    public function show($id)
    {
        $article = ArticleStock::with(['family', 'palette'])->findOrFail($id);
        return response()->json(['data' => $article]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $article = ArticleStock::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'code' => 'sometimes|required|string|max:255|unique:articles,code,' . $id,
            'description' => 'sometimes|required|string',
            'name' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:255',
            'qte_inter' => 'integer|min:0',
            'qte_serie' => 'integer|min:0',
            'palette_id' => 'nullable|exists:palettes,id',
            'family_id' => 'sometimes|required|exists:article_families,id',
            'thickness' => 'nullable|numeric',
            'hieght' => 'nullable|numeric',
            'width' => 'nullable|numeric',
            'depth' => 'nullable|numeric',
            'chant' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $article->update($request->all());

        return response()->json(['data' => $article, 'message' => 'Article updated successfully']);
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
