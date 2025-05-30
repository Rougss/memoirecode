<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Integration extends Model
{
    use HasFactory;

    protected $fillable = [
        'heure',
        'competence_id',
    
    ];

    protected $casts = [
        'heure' => 'datetime:H:i'
    ];

    // Relations
    public function competence()
    {
        return $this->belongsTo(Competence::class, 'competence_id');
    }
  

   

    // Méthodes
    public function planifierEvaluation()
    {
        // Logique pour planifier une évaluation
    }

    public function evaluerEvaluation()
    {
        // Logique pour évaluer une évaluation
    }
}