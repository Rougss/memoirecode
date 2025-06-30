<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Salle extends Model
{
    use HasFactory;

    protected $fillable = [
        'intitule',
        'nombre_de_place',
        'batiment_id'
    ];

    // Relations
    public function batiment()
    {
        return $this->belongsTo(Batiment::class, 'batiment_id');
    }
    public function competences()
    {
        return $this->hasMany(Competence::class);
    }

   

   
   
}