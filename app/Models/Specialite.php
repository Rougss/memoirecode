<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Specialite extends Model
{
    

    protected $fillable = 
    [
        'intitule'
    ];

   public function formateurs(){
    return $this->hasMany(Formateur::class);
   }
}
