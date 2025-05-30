<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Surveillant extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id'
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}