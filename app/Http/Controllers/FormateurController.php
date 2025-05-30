<?php

namespace App\Http\Controllers;

use App\Models\Formateur;
use Illuminate\Http\Request;

class FormateurController extends Controller
{
    // Lister tous les formateurs avec les relations user et spécialité
    public function index()
    {
        $formateurs = Formateur::with(['user', 'specialite'])->get();

        return response()->json([
            'success' => true,
            'data' => $formateurs
        ]);
    }

    // Ajouter un formateur
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'specialite_id' => 'required|exists:specialites,id',
        ]);

        $formateur = Formateur::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Formateur ajouté avec succès',
            'data' => $formateur->load(['user', 'specialite'])
        ], 201);
    }

    // Afficher un formateur spécifique
    public function show($id)
    {
        $formateur = Formateur::with(['user', 'specialite'])->find($id);

        if (!$formateur) {
            return response()->json([
                'success' => false,
                'message' => 'Formateur non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $formateur
        ]);
    }

    // Mettre à jour un formateur
    public function update(Request $request, $id)
    {
        $formateur = Formateur::find($id);

        if (!$formateur) {
            return response()->json([
                'success' => false,
                'message' => 'Formateur non trouvé'
            ], 404);
        }

        $validated = $request->validate([
            'user_id' => 'sometimes|exists:users,id',
            'specialite_id' => 'sometimes|exists:specialites,id',
        ]);

        $formateur->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Formateur mis à jour avec succès',
            'data' => $formateur->load(['user', 'specialite'])
        ]);
    }

    // Supprimer un formateur
    public function destroy($id)
    {
        $formateur = Formateur::find($id);

        if (!$formateur) {
            return response()->json([
                'success' => false,
                'message' => 'Formateur non trouvé'
            ], 404);
        }

        $formateur->delete();

        return response()->json([
            'success' => true,
            'message' => 'Formateur supprimé avec succès'
        ]);
    }
}
