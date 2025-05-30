<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Récupérer le rôle Administrateur
        $adminRole = Role::where('intitule', 'Administrateur')->first();

        // Créer un utilisateur admin
        User::create([
            'nom' => 'Admin',
            'prenom' => 'Principal',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role_id' => $adminRole->id, // si la relation est bien faite
        ]);

        // Créer quelques autres utilisateurs
        User::factory()->count(5)->create(); // nécessite une factory
    }
}
