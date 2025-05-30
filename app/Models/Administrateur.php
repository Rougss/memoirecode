<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Administrateur extends Model
{
    use HasFactory;

    protected $table = 'administrateurs';

    protected $fillable = [
        'user_id',
        
    ];

    protected $casts = [
        'date_nomination' => 'date',
        'permissions_speciales' => 'array',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function departements()
    {
        return $this->hasMany(Departement::class, 'responsable_id');
    }

 

    // Méthodes utilitaires
  
}