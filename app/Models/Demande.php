<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Demande extends Model
{
    protected $fillable = [
        'produit_id',
        'quantite',
    'raison',
    'user_id',
    'etat',
];

    public function user()
{
    return $this->belongsTo(User::class);
}

public function produit()
{
    return $this->belongsTo(Product::class);
}


}