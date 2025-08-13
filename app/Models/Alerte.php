<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Produit;

class Alerte extends Model
{
    protected $fillable = [
        'produit_id',
        'date'
    ];

    public function produit()
    {
        return $this->belongsTo(Product::class, 'produit_id');
    }
}