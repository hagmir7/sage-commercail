<?php

use App\Exports\StockMovementsExport;
use App\Http\Controllers\DocenteteController;
use App\Http\Controllers\SqlEditorController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LogViewerController;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/test/{id}', [DocenteteController::class, 'show']);
Route::post('login', [UserController::class, 'login'])->name("login");

Route::get('sql-editor', [SqlEditorController::class, 'show'])->name('sql-editor.show');
Route::post('sql-editor/execute', [SqlEditorController::class, 'execute'])
    ->name('sql-editor.execute');


 

Route::get('/logs', [LogViewerController::class, 'show'])->name('logs.show');
Route::delete('/logs/clear', [LogViewerController::class, 'clear'])->name('logs.clear');
