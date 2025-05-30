<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    // Lister toutes les intégrations
    public function index()
    {
        $integrations = Integration::with('competence')->get();

        return response()->json([
            'success' => true,
            'data' => $integrations
        ]);
    }

    // Créer une nouvelle intégration
    public function store(Request $request)
    {
        $validated = $request->validate([
            'heure' => 'required|date_format:H:i',
            'competence_id' => 'required|exists:competences,id',
        ]);

        $integration = Integration::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Intégration créée avec succès',
            'data' => $integration->load('competence')
        ], 201);
    }

    // Afficher une intégration
    public function show($id)
    {
        $integration = Integration::with('competence')->find($id);

        if (!$integration) {
            return response()->json([
                'success' => false,
                'message' => 'Intégration non trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $integration
        ]);
    }

    // Mettre à jour une intégration
    public function update(Request $request, $id)
    {
        $integration = Integration::find($id);

        if (!$integration) {
            return response()->json([
                'success' => false,
                'message' => 'Intégration non trouvée'
            ], 404);
        }

        $validated = $request->validate([
            'heure' => 'sometimes|required|date_format:H:i',
            'competence_id' => 'sometimes|required|exists:competences,id',
        ]);

        $integration->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Intégration mise à jour avec succès',
            'data' => $integration->load('competence')
        ]);
    }

    // Supprimer une intégration
    public function destroy($id)
    {
        $integration = Integration::find($id);

        if (!$integration) {
            return response()->json([
                'success' => false,
                'message' => 'Intégration non trouvée'
            ], 404);
        }

        $integration->delete();

        return response()->json([
            'success' => true,
            'message' => 'Intégration supprimée avec succès'
        ]);
    }
}
