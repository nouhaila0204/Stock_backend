<?php

namespace App\Exports; 

use App\Models\Product; 
use Illuminate\Support\Facades\DB;
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
            // Pour les entrées de l'année en cours (2025)
            $entrees = DB::table('entree_product')
                ->where('produit_id', $produit->id)
                ->whereYear('created_at', $this->annee)
                ->sum('quantite'); 
            
            // Pour les sorties de l'année en cours (2025)
            $sorties = DB::table('sortie_product')
                ->where('produit_id', $produit->id)
                ->whereYear('created_at', $this->annee)
                ->sum('quantite'); 
            
            // Stock final = stock actuel du produit (au moment du rapport)
            $stockFinal = $produit->stock; 
            
            // STOCK DÉBUT = Quantité restante de l'année précédente (2024)
            $anneePrecedente = $this->annee - 1;
            
            // 1. D'abord vérifier si le produit existait l'année précédente
            $produitExistait = DB::table('entree_product')
                ->where('produit_id', $produit->id)
                ->whereYear('created_at', '<=', $anneePrecedente)
                ->exists();
            
            if ($produitExistait) {
                // 2. Calculer le solde de toutes les années jusqu'à l'année précédente
                $totalEntreesJusqu2024 = DB::table('entree_product')
                    ->where('produit_id', $produit->id)
                    ->whereYear('created_at', '<=', $anneePrecedente)
                    ->sum('quantite');
                
                $totalSortiesJusqu2024 = DB::table('sortie_product')
                    ->where('produit_id', $produit->id)
                    ->whereYear('created_at', '<=', $anneePrecedente)
                    ->sum('quantite');
                
                // Stock début 2025 = Total entrées jusqu'à 2024 - Total sorties jusqu'à 2024
                $stockDebut = $totalEntreesJusqu2024 - $totalSortiesJusqu2024;
                $stockDebut = max(0, $stockDebut); // Éviter les valeurs négatives
            } else {
                // Si le produit est nouveau en 2025, stock début = 0
                $stockDebut = 0;
            }
            
            // VÉRIFICATION : Le stock final devrait être cohérent
            // Stock final théorique = Stock début + Entrées 2025 - Sorties 2025
            $stockFinalTheorique = $stockDebut + $entrees - $sorties;
            
            // Si incohérence, ajuster (peut arriver si des données ont été modifiées)
            if ($stockFinal != $stockFinalTheorique) {
                // Loguer l'incohérence pour investigation
                \Log::warning("Incohérence stock pour produit {$produit->id}: 
                    Stock final DB = {$stockFinal}, 
                    Stock final théorique = {$stockFinalTheorique}");
            }
            
            // Récupérer le prix unitaire moyen de l'année en cours
            $prixUnitaire = DB::table('entree_product')
                ->where('produit_id', $produit->id)
                ->whereYear('created_at', $this->annee)
                ->avg('prixUnitaire');
            
            $prixUnitaire = $prixUnitaire ?: $produit->price;
            
            $valeurStock = $stockFinal * $prixUnitaire;
            
            $data[] = [ 
                'code' => $produit->reference, 
                'designation' => $produit->name, 
                'stock_initial' => $stockDebut, 
                'entrees' => $entrees, 
                'sorties' => $sorties, 
                'stock_final' => $stockFinal, 
                'prix_unitaire' => $prixUnitaire, 
                'valeur_stock' => $valeurStock, 
            ]; 
        } 
        
        return view('exports.rapport-stock', [ 
            'donnees' => $data, 
            'annee' => $this->annee, 
        ]); 
    } 
}