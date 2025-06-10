<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Chef_Departement extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id'
    ];

     public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function emploiDuTemps()
    {
        return $this->hasMany(EmploiDuTemps::class);
    }


}
