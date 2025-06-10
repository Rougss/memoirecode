<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Niveau extends Model
{
    use HasFactory;

    protected $fillable = [
        'intitule',
       'type_formation_id'
    ];

    // Un niveau peut avoir plusieurs métiers
    public function TypeFormation(){
         return $this->belongsTo(TypeFormation::class, 'type_formation_id');
    }
}
