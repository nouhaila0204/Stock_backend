<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Product;

class Sortie extends Model
{
    protected $fillable = [
        'produit_id',
        'destination',
        'commentaire',
        'quantite',
        'date'
    ];

    public function produit()
    {
        return $this->belongsTo(Product::class, 'produit_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function employe()
{
    return $this->belongsTo(Employe::class);
}

}
