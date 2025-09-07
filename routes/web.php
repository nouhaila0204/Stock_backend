<?php

use Illuminate\Support\Facades\Route;



Route::get('/', function () {
    return view('welcome');
});

 Route::get('/sorties/{id}/bon', [SortieController::class, 'afficherBonSortie']);
 Route::get('/sorties/{id}/imprimer', [SortieController::class, 'imprimerBonSortie']);
