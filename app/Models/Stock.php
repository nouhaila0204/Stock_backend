<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Product;

class Stock extends Model
{
    use HasFactory;

    protected $fillable = [
        'produit_id',
        'qte_entree',
        'qte_sortie',
        'valeur_stock'
    ];

    public function produit()
    {
        return $this->belongsTo(Product::class, 'produit_id');
    }
}