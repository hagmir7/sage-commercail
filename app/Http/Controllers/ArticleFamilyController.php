<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ArticleFamily;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ArticleFamilyController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $families = ArticleFamily::all();
        return response()->json(['data' => $families]);
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
            'code' => 'required|string|max:255|unique:article_families',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $family = ArticleFamily::create($request->all());
        return response()->json(['data' => $family, 'message' => 'Article Family created successfully'], 201);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $family = ArticleFamily::findOrFail($id);
        return response()->json(['data' => $family]);
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
        $family = ArticleFamily::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'code' => 'sometimes|required|string|max:255|unique:article_families,code,' . $id,
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $family->update($request->all());

        return response()->json(['data' => $family, 'message' => 'Article Family updated successfully']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $family = ArticleFamily::findOrFail($id);

        // Check if the family has any articles
        if ($family->articles()->count() > 0) {
            return response()->json(['message' => 'Cannot delete family with associated articles'], 422);
        }

        $family->delete();

        return response()->json(['message' => 'Article Family deleted successfully']);
    }
}
