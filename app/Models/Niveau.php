<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Niveau extends Model
{
    use HasFactory;

    protected $fillable = [
        'intitule',
    ];

    // Un niveau peut avoir plusieurs métiers
    public function metiers()
    {
        return $this->hasMany(Metier::class);
    }
}
