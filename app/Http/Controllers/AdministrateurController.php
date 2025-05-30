<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Administrateur;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
   
    public function index(): JsonResponse
    {
        try {
            $admins = Administrateur::all();

            return response()->json([
                'success' => true,
                'data' => $admins,
                'message' => 'Admins récupérés avec succès'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des admins',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer un nouvel admin avec utilisateur associé
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedUserData = $request->validate([
                'nom' => 'required|string|max:255',
                'prenom' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'telephone' => 'nullable|string|max:20',
                'date_naissance' => 'nullable|date',
                'matricule' => 'required|string|unique:users,matricule',
                'genre' => 'nullable|in:M,F',
                'lieu_naissance' => 'nullable|string|max:255',
                'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            ]);

            // Crée un mot de passe aléatoire (à envoyer à l'admin)

            $photoPath = null;
            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store('photos', 'public');
            }

      

            // Création de l'admin lié
            $admin = Administrateur::create([
             
                // ajoute d'autres champs spécifiques à admin ici si besoin
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'admin' => $admin,
                    'success' => true,
                ],
                'message' => 'Admin créé avec succès'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du type de formation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher un admin spécifique
     */
    public function show(string $id): JsonResponse
    {
        try {
            $admin = Administrateur::with('user')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $admin,
                'message' => 'Admin trouvé'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Admin non trouvé'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'admin',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour un admin et son utilisateur associé
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $admin = Administrateur::findOrFail($id);
            $user = $admin->user;

            $validatedUserData = $request->validate([
                'nom' => 'sometimes|required|string|max:255',
                'prenom' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
                'telephone' => 'nullable|string|max:20',
                'date_naissance' => 'nullable|date',
                'matricule' => 'sometimes|required|string|unique:users,matricule,' . $user->id,
                'genre' => 'nullable|in:M,F',
                'lieu_naissance' => 'nullable|string|max:255',
                'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            ]);

            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store('photos', 'public');
                $validatedUserData['photo'] = $photoPath;
            }

            $user->update($validatedUserData);

            return response()->json([
                'success' => true,
                'data' => $admin->fresh('user'),
                'message' => 'Admin mis à jour avec succès'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Admin non trouvé'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du admin',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer un admin et son utilisateur associé
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $admin = Administrateur::findOrFail($id);
            

            // Supprimer l'admin et ensuite l'utilisateur
            $admin->delete();
     

            return response()->json([
                'success' => true,
                'message' => 'Admin supprimé avec succès'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Admin non trouvé'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'admin',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
