<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Annee extends Model
{
    use HasFactory;

   
    protected $fillable = [
        'intitule',
        'annee',
    ];
    

  

 
    public function emploiDuTemps()
    {
        return $this->hasMany(EmploiDuTemps::class);
    }

    // Méthodes
    public function definirCalendrier()
    {
        
    }
}