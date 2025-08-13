<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TVA extends Model
{
    protected $table = 'tvas';

    protected $fillable = ['nom', 'taux']; // ← Obligatoire

   
    public function produits()
{
    return $this->hasMany(Product::class, 'tva_id'); // 👈 ici : colonne réelle dans la table products
}

}