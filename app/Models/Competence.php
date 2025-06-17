<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Competence extends Model
{
    use HasFactory;

    protected $fillable = [
        'nom',
        'code',
        'numero_competence',
        'quota_horaire',
        'metier_id',
        'formateur_id',
        'salle_id'
    ];

    protected $casts = [
        'quota_horaire' => 'decimal:2'
    ];

    // Relations
    public function metier()
    {
        return $this->belongsTo(Metier::class, 'metier_id');
    }

    public function formateur()
    {
        return $this->belongsTo(Formateur::class, 'formateur_id');
    }

    public function emploiDuTemps()
    {
        return $this->belongsTo(EmploiDuTemps::class,'emploi_du_temps_id');
    }

    public function integrations()
    {
        return $this->hasMany(Integration::class);
    }
    public function compeSemestres()
    {
        return $this->hasMany(CompeSemestre::class);
    }
    public function compEmplois()
    {
        return $this->hasMany(CompEmploi::class);
    }
    public function salle()
    {
        return $this->belongsTo(Salle::class, 'salle_id');
    }
    
}