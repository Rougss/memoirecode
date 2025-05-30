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
        'salle_id',
        'competence_id',
        'annee_id',
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

    public function salle()
    {
        return $this->belongsTo(Salle::class, 'salle_id');
    }

    public function competence()
    {
        return $this->belongsTo(Competence::class, 'competence_id');
    }

    // Méthodes
    public function creerCreneau($data)
    {
        return self::create($data);
    }

    public function modifierCreneau($data)
    {
        return $this->update($data);
    }

    public function supprimerCreneau()
    {
        return $this->delete();
    }

    public function verifierDisponibilite()
    {
        return $this->salle->verifierDisponibilite(
            $this->date_debut,
            $this->heure_debut,
            $this->heure_fin
        ) && $this->competence->formateur->verifierDisponibilite(
            $this->date_debut,
            $this->heure_debut,
            $this->heure_fin
        );
    }
}
