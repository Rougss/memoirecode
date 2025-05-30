<?php

namespace App\Http\Controllers;

use App\Models\Batiment;
use Illuminate\Http\Request;

class BatimentController extends Controller
{
    // Récupérer tous les bâtiments
    public function index()
    {
        return Batiment::all();
    }

    // Créer un nouveau bâtiment
    public function store(Request $request)
    {
        $validated = $request->validate([
            'intitule' => 'required|string|max:255',
        ]);

        $batiment = Batiment::create($validated);

        return response()->json($batiment, 201);
    }

    // Afficher un bâtiment par id
    public function show($id)
    {
        $batiment = Batiment::find($id);

        if (!$batiment) {
            return response()->json(['message' => 'Bâtiment non trouvé'], 404);
        }

        return response()->json($batiment);
    }

    // Mettre à jour un bâtiment
    public function update(Request $request, $id)
    {
        $batiment = Batiment::find($id);

        if (!$batiment) {
            return response()->json(['message' => 'Bâtiment non trouvé'], 404);
        }

        $validated = $request->validate([
            'intitule' => 'required|string|max:255',
        ]);

        $batiment->update($validated);

        return response()->json($batiment);
    }

    // Supprimer un bâtiment
    public function destroy($id)
    {
        $batiment = Batiment::find($id);

        if (!$batiment) {
            return response()->json(['message' => 'Bâtiment non trouvé'], 404);
        }

        $batiment->delete();

        return response()->json(['message' => 'Bâtiment supprimé avec succès']);
    }
}
