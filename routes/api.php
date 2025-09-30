<?php

// use App\Http\Controllers\ArticleFamilyController;
use App\Http\Controllers\CompanyController;
// use App\Http\Controllers\PositionController;
use App\Http\Controllers\ArticleStockController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DepotController;
use App\Http\Controllers\DocenteteController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\EmplacementController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryExportController;
use App\Http\Controllers\InventoryMovementController;
use App\Http\Controllers\LineController;
use App\Http\Controllers\PaletteController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ReceptionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SellController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPermissionController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');




Route::get("duplicate/{piece}", [DocenteteController::class, 'duplicate']);
Route::get("change/{piece}", [DocenteteController::class, 'change']);

Route::get("client/{client}", [ClientController::class, 'show']);
Route::get("article/{article}", [ArticleController::class, 'show']);



Route::get("preparation/{piece}/{companyId}", [PaletteController::class, 'validationCompany']);


Route::get('/users', function (Request $request) {
   return User::all();

});

Route::get('inventory/{inventory}/movements/export', [InventoryExportController::class, 'movements']);

Route::get('inventory/{inventory}/init', [InventoryController::class, 'resetToStock']);
Route::get('inventory/{inventory}/export', [InventoryExportController::class, 'export']);


Route::get("progress/{piece}", [DocenteteController::class, 'progress']);


Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::prefix('user')->controller(UserController::class)->group(function(){
        Route::get('update-password', 'updatePassword');
        Route::post('{userId}/update-password', 'updateUserPassword');
    });

    // Roles
    Route::get('/roles', [RoleController::class, 'index']);
    Route::post('/roles', [RoleController::class, 'store']);
    Route::get('/roles/permissions/{roleName}', [RoleController::class, 'permissions']);

    // Permissions
    Route::get('/permissions', [PermissionController::class, 'index']);
    Route::post('/permissions', [PermissionController::class, 'store']);

    // Assign roles/permissions to users
    Route::get('/users/role/{role}', [UserController::class, 'usersByRole']);
    Route::post('/users/{user}/roles', [UserPermissionController::class, 'assignRoles']);
    Route::post('/role/{roleName}/permissions', [UserPermissionController::class, 'assignPermissions']);

    // Get user Role and permissions
    Route::get('/user/{id}/permissions', [UserPermissionController::class, 'getUserRolesAndPermissions']);
    Route::get('/user/permissions', [UserPermissionController::class, 'getAuthUserRolesAndPermissions']);
    Route::get('roles/{role}', [RoleController::class, 'roleUsers']);


    Route::prefix('depots')->controller(DepotController::class)->group(function () {
        Route::get('/', 'list');
        Route::delete('delete/{depot}', 'delete');
        Route::get('/{depot}', 'show');
        Route::post('/create', 'create');
    });


    Route::prefix('emplacement')->controller(EmplacementController::class)->group(function () {
        Route::post('create', 'create');
        Route::get('{emplacement:code}/inventory/{inventory}', 'showForInventory');
        Route::post('{depot}/import', 'import');
        Route::get('{emplacement:code}', 'show');
        Route::delete('delete/{emplacement:code}', 'delete');
    });

    Route::prefix('transfer')->controller(TransferController::class)->group(function () {
        Route::get('', 'index');
        Route::post('store', 'store');
    });



    Route::prefix('palettes')->controller(PaletteController::class)->group(function () {
        Route::post('generate', 'generate');
        Route::post('scan', 'scanLine');
        Route::get('scan/{code}', 'scanPalette');
        Route::post('confirm', 'confirm');
        Route::post('confirm/{code}/{piece}', 'confirmPalette');
        Route::put('reset/{code}', 'resetPalette');
        Route::post('detach', 'detach');
        Route::post('create', 'create');

        Route::delete('{code}/article/{article_id}/inventory/delete', 'detachArticleForInvenotry');
        Route::delete('{code}/article/{article_id}/delete', 'detachArticle');
        Route::put('{code}/article/{article_id}/update', 'updateArticleQuantity');

        Route::get('{code}', 'show');
        Route::get('{code}/line/{lineId}', 'controller');
        Route::get('document/{piece}', 'documentPalettes');
        Route::delete('{palette:code}', 'destroy');
    });


    Route::prefix('lines')->controller(LineController::class)->group(function () {
        Route::post('prepare', 'prepare');
    });


    Route::prefix('docentetes')->controller(DocenteteController::class)->group(function () {
        Route::get("commercial", 'commercial');
        Route::get("preparation", 'preparation');
        Route::get("fabrication", 'fabrication');
        Route::post("start", 'start');
        Route::post("complation", 'complation');
        Route::get("reset/{piece}", 'reset');
        Route::post("transfer", 'transfer');
        Route::get("shipping", 'shipping');
        Route::post('palettes/validate/{piece}', 'validate');
        Route::post('palettes/validate-partial/{piece}', 'validatePartial');
        Route::get("duplicate/{piece}", 'duplicate');
        Route::get("{id}", 'show');
    });

    


    Route::prefix('stock')->controller(StockMovementController::class)->group(function(){
        Route::post('in', 'in');
        Route::post('out', 'out');
        Route::get('movements', 'listGeneral');
        Route::get('movements/{company}', 'list');
        Route::put('movements/update/{stock_movement}', 'update');
    });



    Route::get("calculator/{piece}", [SellController::class, 'calculator']);

    Route::prefix('inventory-movement')->controller(InventoryMovementController::class)->group(function () {
        Route::put('update/{inventory_movement}', 'updateQuantity');
    });



    Route::prefix('inventory')->controller(InventoryController::class)->group(function () {
        Route::put('palette/{palette:code}/article/{inventory_stock}/update', 'updateArticleQuantityInPalette');
        Route::delete('palette/{palette:code}/article/{inventory_stock}/delete', 'deleteArticleFromPalette');
        Route::get('movements/controlle/{inventory_movement}', 'controlle');
        Route::post('insert/{inventory}', 'insert');
        Route::post('create', 'create');
        Route::get('emplacement/{code}', 'scanEmplacmenet');
        Route::get('article/{code}', 'scanArticle');
        Route::get('list', 'list');
        Route::get('overview/{inventory}', 'overview');
        Route::delete('delete/movement/{inventory_movement}', 'deleteMovement');
        Route::get("articles/{inventory}", 'stockArticle');
        Route::get('{inventory}/depot/{depot}', 'depotEmplacements');
        Route::get("{inventory}", 'show');
    });


    Route::prefix('articles')->controller(ArticleStockController::class)->group(function () {
        Route::get('{article_stock:code}', 'show');
        Route::get('', 'index');
        Route::put('update/{article_stock:code}', 'update');

        Route::get('update/{article:code}', 'update');
        Route::post('import', 'import');
    });


    Route::prefix('companies')->controller(CompanyController::class)->group(function () {
        Route::get('', 'index');
    });


    Route::prefix('documents')->controller(DocumentController::class)->group(function () {

        Route::get('/', 'list');
        Route::get('preparation-list', 'preparationList');

        Route::get('/ready', 'ready');
        Route::get('/progress/{piece}', 'progress');
        Route::post('chargement/{document}', 'addChargement');
        Route::get('validation-controller', 'validationControllerList');

        Route::get('livraison', 'livraison');
        Route::get('print/{document}', 'print');
        Route::get('reset-print/{document}', 'resetPrint');
        // All documents routes up to this route !
        Route::get('{document:piece}', 'show');
        Route::get('/{piece}/palettes', 'documentPalettes');
        Route::get('{piece}/delivered-palettes', 'deliveredPalettes');
        Route::get('/{piece}', 'checkControlled');
    });

    Route::prefix('receptions')->controller(ReceptionController::class)->group(function () {
        Route::get('', 'index');
        Route::get('{piece}', 'show');
        Route::post('transfer', 'transfer');
        Route::get('reset/{piece}', 'reset');
    });


    
});




Route::post('/login', [AuthController::class, 'login']);

Route::post('/register', [AuthController::class, 'register']);

Route::get('/user/{id}', [AuthController::class, 'show']);

Route::post('/user/update/{id}', [UserController::class, 'update']);
