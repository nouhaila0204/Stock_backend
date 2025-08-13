<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FamilleProduit extends Model
{
    // Ajout du champ 'nom' pour autoriser l'attribution en masse
    protected $fillable = ['nom', 'description'];

    public function sousFamilles()
    {
        return $this->hasMany(SousFamilleProduit::class);
    }
    
}