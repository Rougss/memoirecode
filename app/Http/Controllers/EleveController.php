<?php

namespace App\Http\Controllers;

use App\Models\Eleve;
use Illuminate\Http\Request;

class EleveController extends Controller
{
    // Lister tous les élèves avec les relations user et salle
    public function index()
    {
        $eleves = Eleve::with(['user', 'metier'])->get();

        return response()->json([
            'success' => true,
            'data' => $eleves
        ]);
    }

    // Ajouter un élève
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'contact_urgence' => 'nullable|string',
            'metier_id' => 'nullable|exists:metiers,id',
        ]);

        $eleve = Eleve::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Élève ajouté avec succès',
            'data' => $eleve->load(['user', 'metier'])
        ], 201);
    }

    // Afficher un élève spécifique
    public function show($id)
    {
        $eleve = Eleve::with(['user', 'metier'])->find($id);

        if (!$eleve) {
            return response()->json([
                'success' => false,
                'message' => 'Élève non trouvé'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $eleve
        ]);
    }

    // Mettre à jour un élève
    public function update(Request $request, $id)
    {
        $eleve = Eleve::find($id);

        if (!$eleve) {
            return response()->json([
                'success' => false,
                'message' => 'Élève non trouvé'
            ], 404);
        }

        $validated = $request->validate([
            'user_id' => 'sometimes|exists:users,id',
            'contact_urgence' => 'nullable|string',
            'metier_id' => 'nullable|exists:metiers,id',
        ]);

        $eleve->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Élève mis à jour avec succès',
            'data' => $eleve->load(['user', 'metier'])
        ]);
    }

    // Supprimer un élève
    public function destroy($id)
    {
        $eleve = Eleve::find($id);

        if (!$eleve) {
            return response()->json([
                'success' => false,
                'message' => 'Élève non trouvé'
            ], 404);
        }

        $eleve->delete();

        return response()->json([
            'success' => true,
            'message' => 'Élève supprimé avec succès'
        ]);
    }
}
