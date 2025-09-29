<?php

use App\Http\Controllers\DocenteteController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/test/{id}', [DocenteteController::class, 'show']);



// Route::get('login', function(){
//     return response()->json(['message' => 'Unauthorized HTTP responses'], 401);
// })->name("login");


Route::post('login', [UserController::class, 'login'])->name("login");


use App\Http\Controllers\PaletteController;

Route::post('/palettes/import', [PaletteController::class, 'import'])->name('palettes.import');
