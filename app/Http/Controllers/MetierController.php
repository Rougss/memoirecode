<?php

namespace App\Http\Controllers;

use App\Models\Metier;
use Illuminate\Http\Request;

class MetierController extends Controller
{
    // Liste de tous les métiers
    public function index()
    {
        return Metier::all();
    }

    // Création d'un nouveau métier
    public function store(Request $request)
    {
        $validated = $request->validate([
            'intitule' => 'required|string|max:255',
            'duree' => 'required|string|max:255',
            'niveau_id' => 'required|exists:niveaux,id',
            'departement_id' => 'required|exists:departements,id'
        ]);

        $metier = Metier::create($validated);
        return response()->json($metier, 201);
    }

    // Afficher un métier spécifique
    public function show($id)
    {
        $metier = Metier::find($id);
        if (!$metier) {
            return response()->json(['message' => 'Métier non trouvé'], 404);
        }

        return response()->json($metier);
    }

    // Mettre à jour un métier
    public function update(Request $request, $id)
    {
        $metier = Metier::find($id);
        if (!$metier) {
            return response()->json(['message' => 'Métier non trouvé'], 404);
        }

        $validated = $request->validate([
            'intitule' => 'required|string|max:255',
            'duree' => 'required|string|max:255',
            'niveau_id' => 'required|exists:niveaux,id',
            'departement_id' => 'required|exists:departements,id'
        ]);

        $metier->update($validated);
        return response()->json($metier);
    }

    // Supprimer un métier
    public function destroy($id)
    {
        $metier = Metier::find($id);
        if (!$metier) {
            return response()->json(['message' => 'Métier non trouvé'], 404);
        }

        $metier->delete();
        return response()->json(['message' => 'Métier supprimé avec succès']);
    }
}
