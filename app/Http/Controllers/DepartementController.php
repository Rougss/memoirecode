<?php

namespace App\Http\Controllers;

use App\Models\Departement;
use App\Models\Formateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DepartementController extends Controller
{
    // Lister tous les départements
    public function index()
    {
        return Departement::with('metiers', 'batiment', 'chefDepartement.user')->get();
    }

    // 🔥 MÉTHODE CORRIGÉE : Utiliser les bonnes relations
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom_departement' => 'required|string|max:255|unique:departements,nom_departement',
            'batiment_id' => 'required|exists:batiments,id',
            'user_id' => 'nullable|exists:users,id',
            'formateur_id' => 'required|exists:formateurs,id'
        ]);

        Log::info('📝 Données reçues pour création département:', $validated);

        try {
            // Auto-remplir user_id depuis formateur
            $formateur = Formateur::with('user')->find($validated['formateur_id']);
            
            if (!$formateur) {
                return response()->json([
                    'success' => false,
                    'message' => "Formateur avec ID {$validated['formateur_id']} non trouvé"
                ], 404);
            }

            if (!$formateur->user) {
                return response()->json([
                    'success' => false,
                    'message' => "Le formateur {$validated['formateur_id']} n'a pas d'utilisateur associé"
                ], 400);
            }

            // Auto-remplir user_id depuis le formateur
            $validated['user_id'] = $formateur->user_id;
            
            Log::info("✅ user_id auto-rempli: {$validated['user_id']} depuis formateur {$validated['formateur_id']} ({$formateur->user->prenom} {$formateur->user->nom})");

            // Créer le département avec user_id inclus
            $departement = Departement::create([
                'nom_departement' => $validated['nom_departement'],
                'batiment_id' => $validated['batiment_id'],
                'formateur_id' => $validated['formateur_id'],
                'user_id' => $validated['user_id'],
            ]);

            Log::info("✅ Département '{$validated['nom_departement']}' créé avec succès:");
            Log::info("  - ID: {$departement->id}");
            Log::info("  - Chef: {$formateur->user->prenom} {$formateur->user->nom} (Formateur ID: {$validated['formateur_id']}, User ID: {$validated['user_id']})");
            Log::info("  - Bâtiment ID: {$validated['batiment_id']}");

            // ✅ CORRECTION : Utiliser 'chefDepartement' au lieu de 'formateur'
            return response()->json([
                'success' => true,
                'message' => 'Département créé avec succès',
                'data' => $departement->load(['batiment', 'chefDepartement.user'])
            ], 201);

        } catch (\Exception $e) {
            Log::error("❌ Erreur création département: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage()
            ], 500);
        }
    }

    // Afficher un département par son ID
    public function show($id)
    {
        $departement = Departement::with('metiers', 'batiment', 'chefDepartement.user')->find($id);

        if (!$departement) {
            return response()->json(['message' => 'Département non trouvé'], 404);
        }

        return response()->json($departement);
    }

    // Mettre à jour un département
    public function update(Request $request, $id)
    {
        $departement = Departement::find($id);

        if (!$departement) {
            return response()->json(['message' => 'Département non trouvé'], 404);
        }

        $validated = $request->validate([
            'nom_departement' => 'required|string|max:255|unique:departements,nom_departement,' . $id,
            'batiment_id' => 'required|exists:batiments,id',
            'user_id' => 'nullable|exists:users,id',
            'formateur_id' => 'required|exists:formateurs,id'
        ]);

        // Auto-remplir user_id pour update aussi
        if (empty($validated['user_id']) && !empty($validated['formateur_id'])) {
            $formateur = Formateur::with('user')->find($validated['formateur_id']);
            if ($formateur && $formateur->user) {
                $validated['user_id'] = $formateur->user_id;
                Log::info("✅ user_id auto-rempli pour update: {$validated['user_id']}");
            }
        }

        $departement->update($validated);

        return response()->json($departement->load(['batiment', 'chefDepartement.user']));
    }

    // Supprimer un département
    public function destroy($id)
    {
        $departement = Departement::find($id);

        if (!$departement) {
            return response()->json(['message' => 'Département non trouvé'], 404);
        }

        $departement->delete();

        return response()->json(['message' => 'Département supprimé avec succès']);
    }

    public function assignerChef(Request $request, $departementId)
    {
        $validated = $request->validate([
            'formateur_id' => 'required|exists:formateurs,id'
        ]);

        $departement = Departement::findOrFail($departementId);
        
        // Auto-remplir user_id quand on assigne un chef
        $formateur = Formateur::with('user')->find($validated['formateur_id']);
        if ($formateur && $formateur->user) {
            $validated['user_id'] = $formateur->user_id;
        }
        
        $departement->update([
            'formateur_id' => $validated['formateur_id'],
            'user_id' => $validated['user_id'] ?? $departement->user_id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Chef de département assigné avec succès',
            'data' => $departement->load('chefDepartement.user')
        ]);
    }
}