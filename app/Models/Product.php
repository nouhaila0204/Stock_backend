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
    'name', 'reference', 'stock', 'stock_min', 'price',
    'tva_id', 'sous_famille_produit_id'
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
    return $this->hasMany(Entree::class, 'produit_id');
}

public function sorties()
{
    return $this->hasMany(Sortie::class, 'produit_id');
}

public function stock()
{
    return $this->hasMany(Stock::class);
}


}
