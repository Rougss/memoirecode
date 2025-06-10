<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CompEmploi extends Model
{
    use HasFactory;

    protected $table = 'compemplois';

     protected $fillable = [
        'emploi_du_temps_id',
        'competence_id'
    ];
    public function emploiDuTemps()
    {
        return $this->belongsTo(EmploiDuTemps::class,'emploi_du_temps_id');
    }

    public function competence()
    {
        return $this->belongsTo(Competence::class,'competence_id');
    }
}
