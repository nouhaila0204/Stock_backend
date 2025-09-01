<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Employe extends Model
{
    protected $primaryKey = 'user_id'; // ← Définir user_id comme clé primaire
    public $incrementing = false; // ← Désactiver l'auto-increment
    protected $keyType = 'integer'; // ← Type de la clé primaire
    protected $fillable = ['user_id', 'poste'];
    
    public function user()
{
    return $this->belongsTo(User::class);
}

}