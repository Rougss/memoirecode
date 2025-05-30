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
        'salle_id'
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function salle()
    {
        return $this->belongsTo(Salle::class, 'salle_id');
    }

    // Méthodes
    public function consulteEmploiDuTemps()
    {
        return $this->salle->emploiDuTemps;
    }

    public function recevoirNotification($message)
    {
        // Logique pour recevoir une notification
        // Peut utiliser les notifications Laravel
    }
}
