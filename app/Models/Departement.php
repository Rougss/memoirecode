<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Departement extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom_departement',
        'batiment_id',
        'user_id'

    ];

    // Relations
   

    public function metiers()
    {
        return $this->hasMany(Metier::class);
    }
    public function batiment()
    {
        return $this->belongsTo(Batiment::class, 'batiment_id');
    }
    public function users()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
}