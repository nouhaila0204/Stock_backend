<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Entree;
use App\Models\Sortie;
use App\Models\TVA;
use App\Models\SousFamilleProduit;


class Product extends Model
{
protected $table = 'products';

    protected $fillable = [
        'name',
        'reference',
        'stock',
        'stock_min',
        'tva_id',
        'sous_famille_produit_id'
    ];

    protected $attributes = [
        'stock' => 0, // Stock initial à 0 par défaut
    ];

    public function tva()
    {
        return $this->belongsTo(TVA::class);
    }

    public function sousFamille()
    {
        return $this->belongsTo(SousFamilleProduit::class, 'sous_famille_produit_id');
    }

    public function entrees()
{
    return $this->belongsToMany(Entree::class, 'entree_product', 'produit_id', 'entree_id')
                ->withPivot('quantite', 'prixUnitaire', 'quantite_restante')
                ->withTimestamps();
}

     public function sorties()
    {
        return $this->belongsToMany(Sortie::class, 'sortie_product', 'produit_id', 'sortie_id')
                    ->withPivot('quantite')
                    ->withTimestamps();
    }

    public function stock()
    {
        return $this->hasMany(Stock::class, 'produit_id');
    }

   public function calculerValeurStock()
{
    // Récupérer toutes les entrées liées à ce produit via la table pivot
    $entrees = $this->entrees()->withPivot('quantite_restante', 'prixUnitaire')->get();

    $valeurTotale = 0;

    foreach ($entrees as $entree) {
        $valeurTotale += $entree->pivot->quantite_restante * $entree->pivot->prixUnitaire;
    }

    return $valeurTotale;
}

}
