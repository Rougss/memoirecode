<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmploiDuTemps extends Model
{
    use HasFactory;

    protected $table = 'emploi_du_temps';

    protected $fillable = [
        'annee_id',
        'heure_debut',
        'heure_fin',
        'date_debut',
        'date_fin',
        

    ];

    protected $casts = [
        'heure_debut' => 'datetime:H:i',
        'heure_fin' => 'datetime:H:i',
        'date_debut' => 'date',
        'date_fin' => 'date'
    ];

    // Relations
    public function annee()
    {
        return $this->belongsTo(Annee::class, 'annee_id');
    }

     public function compemplois()
    {
        return $this->hasMany(CompEmploi::class, 'emploi_du_temps_id');
    }

    // 👈 ACCESSOR pour récupérer les compétences via la table de liaison
    public function getCompetencesAttribute()
    {
        return $this->compemplois->map(function($compEmploi) {
            return $compEmploi->competence;
        });
    }
    // Pour récupérer le formateur, il faut passer par compemplois → competence → formateur
public function getFormateursAttribute()
{
    return $this->competences->map(function($competence) {
        return $competence->formateur;
    })->unique('id');
}

// Pour récupérer la salle, il faut passer par compemplois → competence → salle
public function getSallesAttribute()
{
    return $this->competences->map(function($competence) {
        return $competence->salle; // Si competence a une salle
    })->filter()->unique('id');
}

   

   

    
}
