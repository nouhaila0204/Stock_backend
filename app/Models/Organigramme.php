<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organigramme extends Model
{
    use HasFactory;

    protected $table = 'organigrammes'; // Assurez-vous que c'est le bon nom de table

    protected $fillable = [
        'nom',
        'type',
        'parent_id'
    ];

    // Relations
    public function parent()
    {
        return $this->belongsTo(Organigramme::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Organigramme::class, 'parent_id');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'organigramme_id');
    }

    public function getDirections()
{
    return Organigramme::where('type', 'direction')->get();
}
public function childrenRecursive()
{
    return $this->hasMany(Organigramme::class, 'parent_id')->with('childrenRecursive');
}


}

