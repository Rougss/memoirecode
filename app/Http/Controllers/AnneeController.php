<?php

namespace App\Http\Controllers;

use App\Models\Annee;
use Illuminate\Http\Request;

class AnneeController extends Controller
{
    // Lister toutes les années
    public function index()
    {
        return response()->json(Annee::all());
    }

    // Créer une nouvelle année
    public function store(Request $request)
    {
        $validated = $request->validate([
            'intitule' => 'required|string|max:255',
            'annee' => 'required|digits:4|integer',
        ]);

        $annee = Annee::create($validated);

        return response()->json($annee, 201);
    }

    // Afficher une année précise
    public function show($id)
    {
        $annee = Annee::find($id);

        if (!$annee) {
            return response()->json(['message' => 'Année non trouvée'], 404);
        }

        return response()->json($annee);
    }

    // Mettre à jour une année
    public function update(Request $request, $id)
    {
        $annee = Annee::find($id);

        if (!$annee) {
            return response()->json(['message' => 'Année non trouvée'], 404);
        }

        $validated = $request->validate([
            'intitule' => 'sometimes|required|string|max:255',
            'annee' => 'sometimes|required|digits:4|integer',
        ]);

        $annee->update($validated);

        return response()->json($annee);
    }

    // Supprimer une année
    public function destroy($id)
    {
        $annee = Annee::find($id);

        if (!$annee) {
            return response()->json(['message' => 'Année non trouvée'], 404);
        }

        $annee->delete();

        return response()->json(['message' => 'Année supprimée']);
    }
}
