<?php

namespace App\Http\Controllers;

use App\Models\Article;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function show(Article $article)
    {
        $article = $article->select("AR_Ref", "AR_Design", "Nom", "FA_CodeFamille", "AR_PrixVen", "Hauteur", "Largeur", "Couleur")
            ->take(15)->get();
        return response()->json($article, 200, [], JSON_INVALID_UTF8_IGNORE);
    }
}
