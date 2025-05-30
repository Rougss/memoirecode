<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\Role;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
     'nom' => fake()->lastName(),
    'prenom' => fake()->firstName(), // ✅ Ajout obligatoire
    'date_naissance' => fake()->date(),
    'telephone' => fake()->phoneNumber(),
    'adresse' => fake()->address(),
    'matricule' => strtoupper(Str::random(10)),
    'photo' => null,
    'lieu_naissance' => fake()->city(),
    'genre' => fake()->randomElement(['M', 'F']),
    'email' => fake()->unique()->safeEmail(),
    'email_verified_at' => now(),
    'password' => Hash::make('password'), // ou bcrypt('password')
    'remember_token' => Str::random(10),
    'role_id' => Role::inRandomOrder()->first()->id,   
     ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
