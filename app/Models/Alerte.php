<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Produit;

class Alerte extends Model
{
    protected $fillable = [
        'produit_id',
        'date', // Date de déclenchement de l'alerte
        'is_viewed' // Indique si l'alerte a été vue (par défaut false)
    ];

    protected $casts = [
        'is_viewed' => 'boolean', // Cast pour gérer true/false
        'date' => 'datetime' // Cast pour gérer la date
    ];

    public function produit()
    {
        return $this->belongsTo(Product::class, 'produit_id');
    }
}