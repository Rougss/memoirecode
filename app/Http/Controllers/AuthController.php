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
     * 🔥 CONNEXION AVEC DEBUG COMPLET
     */
    public function login(Request $request)
    {
        // ✅ DEBUG COMPLET
        \Log::info('=== DÉBUT LOGIN JWT ===');
        \Log::info('Headers reçus:', $request->headers->all());
        \Log::info('Content-Type:', [$request->header('Content-Type')]);
        \Log::info('Body brut:', [$request->getContent()]);
        \Log::info('Request all():', $request->all());
        \Log::info('Email reçu: "' . $request->input('email') . '"');
        \Log::info('Password reçu: "' . $request->input('password') . '"');

        try {
            // ✅ VALIDATION
            $validated = $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            \Log::info('✅ Validation OK:', $validated);

            $credentials = $request->only('email', 'password');
            \Log::info('Credentials préparés:', [
                'email' => $credentials['email'],
                'password_length' => strlen($credentials['password'])
            ]);

            // ✅ VÉRIFIER SI L'UTILISATEUR EXISTE AVANT JWT
            $user = User::where('email', $credentials['email'])->first();
            if (!$user) {
                \Log::error('❌ UTILISATEUR INEXISTANT: ' . $credentials['email']);
                \Log::info('Utilisateurs existants:', User::pluck('email')->toArray());
                
                return response()->json([
                    'success' => false,
                    'message' => 'Utilisateur non trouvé',
                    'debug' => [
                        'email_cherche' => $credentials['email'],
                        'users_db' => User::select('email')->get()
                    ]
                ], 401);
            }

            \Log::info('✅ Utilisateur trouvé: ' . $user->email);
            \Log::info('Hash en base: ' . substr($user->password, 0, 20) . '...');

            // ✅ VÉRIFIER LE MOT DE PASSE MANUELLEMENT
            $passwordMatch = Hash::check($credentials['password'], $user->password);
            \Log::info('Password match: ' . ($passwordMatch ? 'OUI' : 'NON'));

            if (!$passwordMatch) {
                \Log::error('❌ MOT DE PASSE INCORRECT');
                \Log::error('Password fourni: "' . $credentials['password'] . '"');
                \Log::error('Hash attendu: ' . $user->password);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Mot de passe incorrect',
                    'debug' => [
                        'password_provided' => $credentials['password'],
                        'hash_in_db' => substr($user->password, 0, 30) . '...'
                    ]
                ], 401);
            }

            // ✅ TENTATIVE JWT
            \Log::info('Tentative JWT avec credentials...');
            
            if (!$token = JWTAuth::attempt($credentials)) {
                \Log::error('❌ JWT ATTEMPT FAILED');
                
                throw ValidationException::withMessages([
                    'email' => ['Les identifiants fournis sont incorrects.'],
                ]);
            }

            \Log::info('✅ JWT TOKEN CRÉÉ: ' . substr($token, 0, 20) . '...');

            $user = Auth::user();
            /** @var \App\Models\User $user */
            $user = $user->load('role');

            \Log::info('✅ USER CHARGÉ AVEC RÔLE:');
            \Log::info('- Nom: ' . $user->nom . ' ' . $user->prenom);
            \Log::info('- Rôle: ' . ($user->role ? $user->role->intitule : 'AUCUN RÔLE'));

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

        } catch (ValidationException $e) {
            \Log::error('❌ ERREUR VALIDATION:', $e->errors());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors(),
                'debug' => [
                    'request_data' => $request->all(),
                    'validation_rules' => ['email' => 'required|email', 'password' => 'required']
                ]
            ], 422);
            
        } catch (\Exception $e) {
            \Log::error('❌ ERREUR GÉNÉRALE LOGIN:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur serveur: ' . $e->getMessage()
            ], 500);
        }
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
        try {
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
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié'
            ], 401);
        }
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