<?php

namespace App\Http\Controllers;

use App\Models\Semestre;
use Illuminate\Http\Request;

class SemestreController extends Controller
{
    // Lister tous les semestres
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => Semestre::all()
        ]);
    }

    // Créer un semestre
    public function store(Request $request)
    {
        $validated = $request->validate([
            'intitule' => 'required|string|max:255',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date|after_or_equal:date_debut'
        ]);

        $semestre = Semestre::create($validated);

        return response()->json([
            'success' => true,
            'data' => $semestre
        ], 201);
    }

    // Afficher un semestre précis
    public function show($id)
    {
        $semestre = Semestre::find($id);

        if (!$semestre) {
            return response()->json(['message' => 'Semestre non trouvé'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $semestre
        ]);
    }

    // Modifier un semestre
    public function update(Request $request, $id)
    {
        $semestre = Semestre::find($id);

        if (!$semestre) {
            return response()->json(['message' => 'Semestre non trouvé'], 404);
        }

        $validated = $request->validate([
            'intitule' => 'sometimes|required|string|max:255',
        ]);

        $semestre->update($validated);

        return response()->json([
            'success' => true,
            'data' => $semestre
        ]);
    }

    // Supprimer un semestre
    public function destroy($id)
    {
        $semestre = Semestre::find($id);

        if (!$semestre) {
            return response()->json(['message' => 'Semestre non trouvé'], 404);
        }

        $semestre->delete();

        return response()->json([
            'success' => true,
            'message' => 'Semestre supprimé avec succès'
        ]);
    }
}
