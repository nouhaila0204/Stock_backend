<?php

namespace App\Exports; 

use App\Models\Product; 
use App\Models\Entree; 
use App\Models\Sortie; 
use Illuminate\Contracts\View\View; 
use Maatwebsite\Excel\Concerns\FromView; 

class RapportStockExport implements FromView 
{ 
    protected $annee; 
    public function __construct($annee) 
    { 
        $this->annee = $annee; 
    } 
    public function view(): View 
    { 
        $produits = Product::with('tva')->get(); 
        $data = []; 
        foreach ($produits as $produit) { 
            $entrees = Entree::where('produit_id', $produit->id)
                ->whereYear('created_at', $this->annee)
                ->sum('quantite'); 
            $sorties = Sortie::where('produit_id', $produit->id)
                ->whereYear('created_at', $this->annee)
                ->sum('quantite'); 
            $stockFinal = $produit->stock; 
            $stockInitial = $stockFinal + $sorties - $entrees; 
            $valeurStock = $stockFinal * $produit->price; 
            $data[] = [ 
                'code' => $produit->reference, 
                'designation' => $produit->name, 
                'stock_initial' => $stockInitial, 
                'entrees' => $entrees, 
                'sorties' => $sorties, 
                'stock_final' => $stockFinal, 
                'prix_unitaire' => $produit->price, 
                'valeur_stock' => $valeurStock, 
            ]; 
        } 
        return view('exports.rapport-stock', [ 
            'donnees' => $data, 
            'annee' => $this->annee, 
        ]); 
    } 
}