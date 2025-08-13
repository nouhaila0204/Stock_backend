<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Product;

class Entree extends Model
{
    protected $fillable = ['produit_id','numBond','codeMarche','prixUnitaire','quantite','date','fournisseur_id'];

    public function produit()
    {
        return $this->belongsTo(Product::class, 'produit_id');
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



