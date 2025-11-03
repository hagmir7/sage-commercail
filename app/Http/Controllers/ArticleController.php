<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ArticleController extends Controller
{

    public function search(Request $request)
    {
        $query = $request->input('q');

        $articles = Article::select('AR_Ref', 'AR_Design')
            ->when($query, function ($q) use ($query) {
                $q->where('AR_Ref', 'like', '%' . $query . '%')
                    ->orWhere('AR_Design', 'like', '%' . $query . '%');
            })
            ->paginate(100);

        return response()->json($articles);
    }


    public function show(Article $article)
    {

        $article = $article->select("AR_Ref", "AR_Design", "Nom", "FA_CodeFamille", "AR_PrixVen", "Hauteur", "Largeur", "Couleur")
            ->take(15)->get();
        return response()->json($article, 200, [], JSON_INVALID_UTF8_IGNORE);
    }


    public function update(Article $article){
        
    }


    public function updateRef(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'old_ref' => "required|string|max:50",
                'new_ref' => "required|string|max:50",
                'compnay_db' => 'required'
            ],
            [],
            [
                'old_ref' => 'ancienne référence',
                'new_ref' => 'nouvelle référence',
                'compnay_db' => 'base de données de l\'entreprise',
            ]
        );


        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors(),
                'message' => $validator->errors()->first()
            ], 422);
        }

        if (!Article::on($request->compnay_db)->where('AR_Ref', $request->old_ref)->exists()) {
            return response()->json([
                'message' => "Référence n'existe pas : {$request->old_ref}"
            ], 404);
        }

        if (Article::on($request->compnay_db)->where('AR_Ref', $request->new_ref)->exists()) {
            return response()->json([
                'message' => "Référence existe déjà : {$request->new_ref}"
            ], 409);
        }


        DB::connection($request->compnay_db)->statement(
            "EXEC dbo.sp_UpdateArticleRef @OldRef = ?, @NewRef = ?",
            [$request->old_ref, $request->new_ref]
        );

        return response()->json([
            'message' => "La référence a été mise à jour avec succès : {$request->old_ref} ➝ {$request->new_ref}"
        ], 200);
    }



}
