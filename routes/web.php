<?php

use App\Http\Controllers\DocenteteController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test/{id}', [DocenteteController::class, 'show']);
Route::post('login', [UserController::class, 'login'])->name("login");



