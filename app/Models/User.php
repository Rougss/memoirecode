<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use App\Models\Role;



class User extends Authenticatable implements JWTSubject
{
    use  HasFactory, Notifiable;


    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'date_naissance',
        'telephone',
        'matricule',
        'genre',
        'lieu_naissance',
       
        'photo',
        'role_id',
        'password'
    ];

    protected $hidden = [
        'password',
      
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'date_naissance' => 'date',
        'password' => 'hashed',
    ];

    // Relations
    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function formateur()
    {
        return $this->hasOne(Formateur::class);
    }

    public function eleve()
    {
        return $this->hasOne(Eleve::class);
    }

    public function surveillant()
    {
        return $this->hasOne(Surveillant::class);
    }

    public function directeurDesEtude()
   {
    return $this->hasOne(DirecteurDesEtude::class);
    }

   public function administrateur()
    {
        return $this->hasOne(Administrateur::class);
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }
    
    public function getJWTCustomClaims()
    {
        return [];
    }
    
   

  
  
}
 