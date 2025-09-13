<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Product;

class Entree extends Model
{
    use HasFactory;

    protected $fillable = ['numBond', 'codeMarche', 'date', 'fournisseur_id'];

    public function produits()
    {
    return $this->belongsToMany(Product::class, 'entree_product', 'entree_id', 'produit_id')
        ->withPivot('quantite', 'prixUnitaire', 'quantite_restante') 
        ->withTimestamps();
    }


    public function fournisseur()
    {
        return $this->belongsTo(Fournisseur::class, 'fournisseur_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}



