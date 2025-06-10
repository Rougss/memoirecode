<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Metier extends Model
{
    use HasFactory;

    protected $fillable = [
        'duree',
        'intitule',
        'niveau_id',
        'departement_id'
    ];

    // Relations
    public function niveau()
    {
        return $this->belongsTo(Niveau::class, 'niveau_id');
    }

    
    public function departement()
    {
        return $this->belongsTo(Departement::class, 'departement_id');
    }

    public function competences()
    {
        return $this->hasMany(Competence::class);
    }

}