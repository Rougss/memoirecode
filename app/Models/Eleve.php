<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Eleve extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_urgence',
        'user_id',
        'metier_id'
    ];

    // Relations
    public function user()
    { 
        return $this->belongsTo(User::class, 'user_id');
    }

    public function metier()
    {
        return $this->belongsTo(metier::class, 'metier_id');
    }


}
