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
        'formateur_id'
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
        return $this->hasMany(EmploiDuTemps::class);
    }

    public function integrations()
    {
        return $this->hasMany(Integration::class);
    }
}