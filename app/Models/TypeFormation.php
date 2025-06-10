<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TypeFormation extends Model
{
    use HasFactory;

    protected $table = 'type_formations';

    protected $fillable = [
        'intitule',
    ];

   
   public function niveaux()
    {
        return $this->hasMany(Niveau::class);
    }

}
