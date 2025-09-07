<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\EntreeController;
use App\Http\Middleware\ResponsableStockMiddleware;



Route::get('/', function () {
    return view('welcome');
});

Route::get('/sorties/{id}/bon', [SortieController::class, 'afficherBonSortie']);
Route::get('/sorties/{id}/imprimer', [SortieController::class, 'imprimerBonSortie']);

Route::middleware(['auth:sanctum', ResponsableStockMiddleware::class])->group(function () {
    Route::get('/entrees/{id}/bon', [EntreeController::class, 'afficherBonEntree'])->name('entrees.bon');
    Route::get('/entrees/{id}/imprimer', [EntreeController::class, 'imprimerBon'])->name('entrees.imprimer');
});

// Route catch-all pour l'application React (doit être en dernier)
Route::get('/{any}', function () {
    return view('app'); // Vue qui charge votre application React
})->where('any', '.*');
?>
