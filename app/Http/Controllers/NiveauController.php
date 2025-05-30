<?php

namespace App\Http\Controllers;

use App\Models\Niveau;
use Illuminate\Http\Request;

class NiveauController extends Controller
{
    // Liste tous les niveaux
    public function index()
    {
        return Niveau::all();
    }

    // Créer un nouveau niveau
    public function store(Request $request)
    {
        $validated = $request->validate([
            'intitule' => 'required|string|max:255|unique:niveaux,intitule',
        ]);

        $niveau = Niveau::create($validated);

        return response()->json($niveau, 201);
    }

    // Affiche un niveau spécifique
    public function show($id)
    {
        $niveau = Niveau::with('metiers')->find($id);

        if (!$niveau) {
            return response()->json(['message' => 'Niveau non trouvé'], 404);
        }

        return response()->json($niveau);
    }

    // Met à jour un niveau
    public function update(Request $request, $id)
    {
        $niveau = Niveau::find($id);

        if (!$niveau) {
            return response()->json(['message' => 'Niveau non trouvé'], 404);
        }

        $validated = $request->validate([
            'intitule' => 'required|string|max:255|unique:niveaux,intitule,' . $niveau->id,
        ]);

        $niveau->update($validated);

        return response()->json($niveau);
    }

    // Supprime un niveau
    public function destroy($id)
    {
        $niveau = Niveau::find($id);

        if (!$niveau) {
            return response()->json(['message' => 'Niveau non trouvé'], 404);
        }

        $niveau->delete();

        return response()->json(['message' => 'Niveau supprimé avec succès']);
    }
}
