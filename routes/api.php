<?php

// use App\Http\Controllers\ArticleFamilyController;
// use App\Http\Controllers\CompanyController;
// use App\Http\Controllers\PositionController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DepotController;
use App\Http\Controllers\DocenteteController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\PaletteController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SellController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPermissionController;
use App\Models\Docentete;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');




Route::get("client/{client}", [ClientController::class, 'show']);
Route::get("article/{article}", [ArticleController::class, 'show']);

Route::prefix('documents')->controller(DocumentController::class)->group(function () {
    Route::get('/', 'list');
    Route::get('/{piece}', 'checkControlled');
    Route::get('/progress/{piece}', 'progress');
});



Route::get("preparation/{piece}/{companyId}", [PaletteController::class, 'validationCompany']);


Route::get('/users', function (Request $request) {
    return User::all();
});

Route::get("progress/{piece}", [DocenteteController::class, 'progress']);


Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Roles
    Route::get('/roles', [RoleController::class, 'index']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::get('/roles/permissions/{roleName}', [RoleController::class, 'permissions']);

    // Permissions
    Route::get('/permissions', [PermissionController::class, 'index']);
    Route::post('/permissions', [PermissionController::class, 'store']);

    // Assign roles/permissions to users
    Route::post('/users/{user}/roles', [UserPermissionController::class, 'assignRoles']);
    Route::post('/role/{roleName}/permissions', [UserPermissionController::class, 'assignPermissions']);

    // Get user Role and permissions
    Route::get('/user/{id}/permissions', [UserPermissionController::class, 'getUserRolesAndPermissions']);
    Route::get('/user/permissions', [UserPermissionController::class, 'getAuthUserRolesAndPermissions']);
    //

    Route::get("docentete/validation", [DocenteteController::class, 'validation']);
    Route::post("docentete/transfer", [DocenteteController::class, 'transfer']);
    Route::get("docentete/shipping", [DocenteteController::class, 'shipping']);
    Route::get("docentetes/commercial", [DocenteteController::class, 'commercial']);
    Route::get("docentetes/preparation", [DocenteteController::class, 'preparation']);
    Route::get("docentetes/fabrication", [DocenteteController::class, 'fabrication']);
    Route::get("docentete/{id}", [DocenteteController::class, 'show']);


    Route::post("docentetes/start", [DocenteteController::class, 'start']);

    Route::post("docentetes/complation", [DocenteteController::class, 'complation']);
    // http://localhost:8000/api/palettes/PB00000020
    Route::get("docentetes/reset/{piece}", [DocenteteController::class, 'reset']);

    Route::post('palettes/validate/{piece}', [DocenteteController::class, 'validate']);
    Route::post('palettes/generate', [PaletteController::class, 'generate']);
    Route::post('palettes/scan', [PaletteController::class, 'scan']);
    Route::post('palettes/confirm', [PaletteController::class, 'confirm']);
    Route::post('palettes/detach', [PaletteController::class, 'detach']);
    Route::post('palettes/create', [PaletteController::class, 'create']);
    Route::get('palettes/{code}', [PaletteController::class, 'show']);
    Route::get('palettes/{code}/line/{lineId}', [PaletteController::class, 'controller']);


    // 
    Route::get('palettes/document/{piece}', [PaletteController::class, 'documentPalettes']);
    
    Route::get("calculator/{piece}", [SellController::class, 'calculator']);



    // Test
    // Route::get("docentete/validation/{id}", [DocenteteController::class, 'validation']);
});


Route::post('/login', [AuthController::class, 'login']);

Route::post('/register', [AuthController::class, 'register']);

Route::get('/user/{id}', [AuthController::class, 'show']);

Route::post('/user/update/{id}', [UserController::class, 'update']);

// Pallets
Route::prefix('v1')->group(function () {
    // Companies
    // Route::apiResource('companies', CompanyController::class);

    // Depots
    Route::apiResource('depots', DepotController::class);

    // Positions
    // Route::apiResource('positions', PositionController::class);

    // Palettes
    Route::apiResource('palettes', PaletteController::class);

    // Article Families
    // Route::apiResource('article-families', ArticleFamilyController::class);

    // Articles
    Route::apiResource('articles', ArticleController::class);
});

