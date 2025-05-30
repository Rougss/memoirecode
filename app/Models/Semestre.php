<?php

namespace App\Models; // Ajout du namespace
use App\Models\Competence;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Semestre extends Model
{
    use HasFactory;

    protected $fillable = [
        'intitule',
        'date_debut',
        'date_fin'
        
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date'
    ];

  public function competences()
    {
        return $this->hasMany(Competence::class);
    }

   


}
