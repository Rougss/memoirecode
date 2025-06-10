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

   

   

    
}
