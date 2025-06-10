<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CompeSemestre extends Model
{
    use HasFactory;
     protected $table = 'compesemestres';

    protected $fillable = [
        'semestre_id',
        'competence_id',
    ];

    public function competence()
    {
        return $this->belongsTo(Competence::class, 'competence_id');
    }

    public function semestre()
    {
        return $this->belongsTo(Semestre::class, 'semestre_id');
    }
}
