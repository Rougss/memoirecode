<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Batiment extends Model
{
    use HasFactory;

    protected $fillable = [
        'intitule',
      
    ];

    // Relations
    public function salles()
    {
        return $this->hasMany(Salle::class);
    }

    public function departements()
    {
        return $this->hasMany(Departement::class);
    }

}