<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Product;

class Sortie extends Model
{
    use HasFactory;

    protected $fillable = [
        'destination',
        'commentaire',
        'date',
    ];

    public function produits()
    {
        return $this->belongsToMany(Product::class, 'sortie_product', 'sortie_id', 'produit_id')
                    ->withPivot('quantite')
                    ->withTimestamps();
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
