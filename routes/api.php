<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\ResponsableStockMiddleware;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\EmployeMiddleware;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TVAController;
use App\Http\Controllers\FamilleProduitController;
use App\Http\Controllers\SousFamilleProduitController;
use App\Http\Controllers\EntreeController;
use App\Http\Controllers\SortieController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\AlerteController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\DemandeController;
use App\Http\Controllers\ResponsableStockController;
use App\Http\Controllers\EmployeController;
use App\Http\Controllers\FournisseurController;

// Routes publiques (pas besoin d'authentification)
Route::post('/login', [AuthController::class, 'login']);

// Routes protégées (nécessitent une authentification)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/logout-all', [AuthController::class, 'logoutAll']);


Route::middleware(['auth:sanctum', ResponsableStockMiddleware::class])->group(function () {
//entrees
   Route::get('/entrees/filtrer', [EntreeController::class, 'filtrer']);
   Route::get('/entrees', [EntreeController::class, 'showEntree']);
   Route::post('/entrees', [EntreeController::class, 'ajouterEntree']);
   Route::delete('/entrees/{id}', [EntreeController::class, 'suppEntree']);
   Route::put('/entrees/{id}', [EntreeController::class, 'updateEntree']);
   
   Route::get('/entrees/{id}/bon', [EntreeController::class, 'afficherBonEntree'])
        ->name('entrees.bon');// Route pour afficher le bon d'entrée (HTML)
   Route::get('/entrees/{id}/imprimer', [EntreeController::class, 'imprimerBon'])
        ->name('entrees.imprimer');// Route pour imprimer/télécharger le bon d'entrée (PDF)


//sorties
    Route::get('/sorties/search', [SortieController::class, 'search']);
    Route::get('/sorties', [SortieController::class, 'indexSortie']);
    Route::get('/sorties/{id}', [SortieController::class, 'showSortie']);
    Route::post('/sorties', [SortieController::class, 'ajouterSortie']);
    Route::put('/sorties/{id}', [SortieController::class, 'updateSortie']);
    Route::delete('/sorties/{id}', [SortieController::class, 'suppSortie']);
    Route::get('/sorties/filtrer', [SortieController::class, 'filtrer']); // ✅ Place-la ici AVANT /{id}
    Route::get('/sorties/{id}', [SortieController::class, 'showSortie']);
    // Route pour afficher le bon de sortie (HTML)
    Route::get('/sorties/{id}/bon', [SortieController::class, 'afficherBonSortie'])
        ->name('sorties.bon');
    // Route pour imprimer/télécharger le bon de sortie (PDF)
    Route::get('/sorties/{id}/imprimer', [SortieController::class, 'imprimerBonSortie'])
        ->name('sorties.imprimer');

//stock
    Route::get('/stock/filtrer', [StockController::class, 'filtrer']);
    Route::get('/stock', [StockController::class, 'index']);
    Route::get('/stock/{id}', [StockController::class, 'show']);
    Route::get('/stock/export-stock/{annee}', [StockController::class, 'exportStock']);

    //alertes
    Route::apiResource('alertes', AlerteController::class);

    //demandes
    Route::apiResource('demandes', DemandeController::class);

    // 🔐 Produits
    Route::get('products/search', [ProductController::class, 'search']);
    Route::get('/products', [ProductController::class, 'showProduit']);
    Route::post('/products', [ProductController::class, 'ajouterProduit']);
    Route::put('/products/{id}', [ProductController::class, 'updateProduit']);
    Route::delete('/products/{id}', [ProductController::class, 'suppProduit']);


    // 🔐 TVA
    Route::get('tvas/rechercher', [TVAController::class, 'rechercher']);
    Route::apiResource('tvas', TVAController::class);
    Route::get('/tvas', [TVAController::class, 'index']);
    Route::get('/tvas/{id}', [TVAController::class, 'Show']);
    Route::post('/tvas', [TVAController::class, 'store']);
    Route::put('/tvas/{id}', [TVAController::class, 'update']);
    Route::delete('/tvas/{id}', [TVAController::class, 'destroy']);

    // 🔐 Familles
    Route::get('familles/search', [FamilleProduitController::class, 'search']);
    Route::apiResource('familles', FamilleProduitController::class);
    Route::get('/familles', [FamilleProduitController::class, 'index']);
    Route::get('/familles/{id}', [FamilleProduitController::class, 'show']);
    Route::post('/familles', [FamilleProduitController::class, 'store']);
    Route::put('/familles/{id}', [FamilleProduitController::class, 'update']);
    Route::delete('/familles/{id}', [FamilleProduitController::class, 'destroy']);

    // 🔐 Sous-familles
    Route::get('sous-familles/search', [SousFamilleProduitController::class, 'search']);
    Route::apiResource('sous-familles', SousFamilleProduitController::class);
    Route::get('/sous-familles', [SousFamilleProduitController::class, 'index']);
    Route::get('/sous-familles/{id}', [SousFamilleProduitController::class, 'show']);
    Route::post('/sous-familles', [SousFamilleProduitController::class, 'store']);
    Route::put('/sous-familles/{id}', [SousFamilleProduitController::class, 'update']);
    Route::delete('/sous-familles/{id}', [SousFamilleProduitController::class, 'destroy']);


    Route::get('/demande/{etat}', [ResponsableStockController::class, 'demandesParEtat']);
    Route::put('/demande/{id}/valider', [ResponsableStockController::class, 'validerDemande']);
    Route::put('/demande/{id}/refuser', [ResponsableStockController::class, 'refuserDemande']);
    Route::put('/demande/{id}/changer-etat', [ResponsableStockController::class, 'changerEtatDemande']);

    Route::get('/fournisseurs', [ResponsableStockController::class, 'indexFournisseurs']);
    Route::post('/fournisseurs', [ResponsableStockController::class, 'storeFournisseur']);
    Route::put('/fournisseurs/{id}', [ResponsableStockController::class, 'updateFournisseur']);
    Route::delete('/fournisseurs/{id}', [ResponsableStockController::class, 'destroyFournisseur']);

    Route::get('/alertes', [ResponsableStockController::class, 'voirAlertes']);

    Route::get('/statistiques', [ResponsableStockController::class, 'statistiquesGlobales']);

});


   //admin
    Route::middleware(['auth:sanctum', AdminMiddleware::class])->group(function () {
    Route::post('/addUsers', [AuthController::class, 'register']);// ➕ Ajouter un utilisateur 
    Route::get('/usersview', [AdminController::class, 'indexUser']);          // 🔍 Voir tous les utilisateurs
    Route::put('/UPusers/{id}', [AdminController::class, 'updateUser']);    // ✏ Modifier un utilisateur
    Route::delete('/suppusers/{id}', [AdminController::class, 'destroyUser']); // ❌ Supprimer un utilisateur


   Route::get('/organigrammes', [AdminController::class, 'organigrammeIndex']);       // Tous
   Route::get('/organigrammes/search', [AdminController::class, 'organigrammeSearch']);
    Route::get('/organigrammes/{id}', [AdminController::class, 'organigrammeShow']);   // Un seul
    Route::post('/addOrganigrammes', [AdminController::class, 'organigrammeStore']);      // Créer
    Route::put('/organigrammes/{id}', [AdminController::class, 'organigrammeUpdate']); // Modifier
    Route::delete('/organigrammes/{id}', [AdminController::class, 'organigrammeDestroy']); // Supprimer
    
});



//employe
Route::middleware(['auth:sanctum', EmployeMiddleware::class])->group(function () {
    Route::get('/employe/products', [ProductController::class, 'indexProduit']);
    Route::get('/employe/voirStock', [EmployeController::class, 'consulterStock']);
    Route::get('/employe/historiquesdemandes', [EmployeController::class, 'consulterHistoriqueDemande']);
    Route::post('/employe/demandes', [EmployeController::class, 'storeDemande']);
    Route::get('/employe/demandes/{id}', [EmployeController::class, 'showDemande']);
    Route::put('/employe/demandes/{id}', [EmployeController::class, 'updateDemande']);
    Route::delete('/employe/demandes/{id}', [EmployeController::class, 'deleteDemande']);
});

   
});