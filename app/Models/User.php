<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * Les attributs modifiables.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'organigramme_id',
    ];

    /**
     * Les attributs cachés.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Les castings de champs.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Relations
public function demandes()
{
    return $this->hasMany(Demande::class, 'user_id');
}

    public function employe()
    {
        return $this->hasOne(Employe::class);
    }

    public function organigramme()
    {
        return $this->belongsTo(Organigramme::class, 'organigramme_id');
    }


    // Vérifications de rôle
    public function isResponsableStock()
    {
        return $this->role === 'responsablestock';
    }

    public function isEmploye()
    {
        return $this->role === 'employe';
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }
    public function gererUtilisateurs()
{
    // Exemple simple : retourner tous les utilisateurs (à adapter selon ton besoin réel)
    return self::all();
}
public function validerDemande($demande)
{
    // Logique de validation de la demande (à adapter)
    $demande->statut = 'validée';
    $demande->save();
}

public function genererRapportStock()
{
    // Tu peux récupérer toutes les données de stock ici
    return \App\Models\Stock::with('produit')->get();
}
    
}