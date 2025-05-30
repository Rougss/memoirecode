<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Connexion utilisateur (JWT)
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants fournis sont incorrects.'],
            ]);
        }

        $user = Auth::user();
/** @var \App\Models\User $user */
$user = $user->load('role');


        return response()->json([
            'success' => true,
            'message' => 'Connexion réussie',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'nom' => $user->nom,
                    'prenom' => $user->prenom,
                    'email' => $user->email,
                    'matricule' => $user->matricule,
                    'photo' => $user->photo,
                    'role' => $user->role
                ],
                'token' => $token
            ]
        ]);
    }

    /**
     * Déconnexion (invalider le token)
     */
    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());

            return response()->json([
                'success' => true,
                'message' => 'Déconnexion réussie',
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Impossible de déconnecter',
            ], 500);
        }
    }

    /**
     * Informations de l'utilisateur connecté
     */
    public function me()
    {
        $user = Auth::user();
/** @var \App\Models\User $user */
$user = $user->load('role');


        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'nom' => $user->nom,
                'prenom' => $user->prenom,
                'email' => $user->email,
                'matricule' => $user->matricule,
                'telephone' => $user->telephone,
                'date_naissance' => $user->date_naissance,
                'genre' => $user->genre,
                'lieu_naissance' => $user->lieu_naissance,
                'photo' => $user->photo,
                'role' => $user->role,
            ]
        ]);
    }

    /**
     * Enregistrement (désactivé)
     */
    public function register(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Enregistrement non autorisé. Contactez l\'administrateur.',
        ], 403);
    }
}
