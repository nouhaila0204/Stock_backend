<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fournisseur extends Model
{
    protected $fillable = ['raisonSocial', 'email', 'adresse', 'telephone'];

    public function entrees()
    {
        return $this->hasMany(Entree::class);
    }
}