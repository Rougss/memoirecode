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

    public function eleves()
    {
        return $this->hasMany(Eleve::class);
    }

    public function emploiDuTemps()
    {
        return $this->hasMany(EmploiDuTemps::class);
    }

    // Méthodes
    public function verifierDisponibilite($date, $heure_debut, $heure_fin)
    {
        return !$this->emploiDuTemps()
            ->where('date_debut', '<=', $date)
            ->where('date_fin', '>=', $date)
            ->where(function($query) use ($heure_debut, $heure_fin) {
                $query->whereBetween('heure_debut', [$heure_debut, $heure_fin])
                      ->orWhereBetween('heure_fin', [$heure_debut, $heure_fin]);
            })->exists();
    }

    public function reserverSalle($date, $heure_debut, $heure_fin)
    {
        if($this->verifierDisponibilite($date, $heure_debut, $heure_fin)) {
            // Logique de réservation
            return true;
        }
        return false;
    }

    public function libererSalle($emploi_id)
    {
        $this->emploiDuTemps()->where('id', $emploi_id)->delete();
    }
}