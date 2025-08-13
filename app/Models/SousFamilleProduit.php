<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SousFamilleProduit extends Model
{
    protected $fillable = ['nom', 'description', 'famille_produit_id'];

    public function famille()
    {
        return $this->belongsTo(FamilleProduit::class, 'famille_produit_id');
    }

    public function produit()
    {
        return $this->hasMany(Product::class);
    }
    
}