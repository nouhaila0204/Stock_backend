<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Organigramme extends Model
{
    protected $fillable = ['nom', 'type', 'parent_id'];

    public function parent()
    {
        return $this->belongsTo(Organigramme::class, 'parent_id');
    }

    public function enfants()
    {
        return $this->hasMany(Organigramme::class, 'parent_id');
    }

    public function user()
    {
        return $this->hasMany(User::class, 'user_id');
    }
}

