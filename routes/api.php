<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DocenteteController;
use App\Models\Docentete;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



Route::get("docentetes/{status}", [DocenteteController::class, 'index']);
Route::get("docentete/{id}", [DocenteteController::class, 'show']);

Route::get("client/{client}", [ClientController::class, 'show']);
Route::get("article/{article}", [ArticleController::class, 'show']);



Route::get('/test-db', function () {
    $records = Docentete::select("DO_Type", 'DO_Piece', "CT_NumPayeur", "cbMarq", "DO_Ref", "DO_Statut", "cbCreation", "DO_Souche", 'DO_TotalHT', 'DO_Imprim')
    ->orderByDesc('cbCreation')->paginate(20);
    return response(json_encode($records, JSON_INVALID_UTF8_IGNORE), 200, ['Content-Type' => 'application/json']);
});