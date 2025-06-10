<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Formateur extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'specialite_id',
    
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function specialite()
    {
        return $this->belongsTo(Specialite::class, 'specialite_id');
    }
    public function competences()
    {
        return $this->hasMany(Competence::class);
    }
    public function formaDepart()
    {
        return $this->hasMany(FormaDepart::class);
    }
 
}