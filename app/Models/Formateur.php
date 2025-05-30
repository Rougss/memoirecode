<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Formateur extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
         // Assurez-vous que cette colonne existe dans la table formateurs
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
 
}