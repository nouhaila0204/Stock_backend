<?php

namespace App\Http\Controllers;

use App\Models\Stock;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\RapportStockExport;



class StockController extends Controller
{

/*public function autoriserResponsable()
{
if (!auth()->user() || !auth()->user()->isResponsableStock()) {
abort(403, 'Non autorisé');
}*/


    // 📦 Afficher tous les stocks
    public function index()
    {
        return Stock::with('produit')->get();
    }
    
    // 📦 Afficher un stock spécifiqu
    public function show($id)
    {
        $stock = Stock::with('produit')->findOrFail($id);
        return response()->json($stock);
    }

    public function filtrer(Request $request)
    {
        $query = Stock::with('produit');


    if ($request->filled('produit_id')) {
        $query->where('produit_id', $request->produit_id);
    }

    if ($request->filled('min_entree')) {
        $query->where('qteEntree', '>=', $request->min_entree);
    }

    if ($request->filled('max_sortie')) {
        $query->where('qteSortie', '<=', $request->max_sortie);
    }

    return response()->json($query->get());
}

    public function exportStock($annee) {

        $nomFichier = 'rapport-stock-' . $annee . '.xlsx';
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\RapportStockExport($annee), $nomFichier);
    }
}


