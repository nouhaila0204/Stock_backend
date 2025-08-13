<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Employe extends Model
{
    protected $fillable = ['user_id', 'poste'];
    
    public function user()
{
    return $this->belongsTo(User::class);
}

}