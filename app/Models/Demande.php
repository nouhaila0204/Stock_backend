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

public function products()
    {
        return $this->belongsToMany(Product::class, 'demande_product')
                    ->withPivot('quantite')
                    ->withTimestamps();
    }



}