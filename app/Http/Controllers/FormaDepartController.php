<?php

namespace App\Http\Controllers;

use App\Models\FormaDepart;
use Illuminate\Http\Request;

class FormaDepartController extends Controller
{
    // Affiche toutes les relations formateur-département
    public function index()
    {
        $relations = FormaDepart::with(['formateur', 'departement'])->get();
        return response()->json($relations);
    }

    // Crée une nouvelle relation formateur-département
    public function store(Request $request)
    {
        $validated = $request->validate([
            'formateur_id' => 'required|exists:formateurs,id',
            'departement_id' => 'required|exists:departements,id',
        ]);

        $relation = FormaDepart::create($validated);
        return response()->json($relation, 201);
    }

    // Affiche une relation en particulier
    public function show($id)
    {
        $relation = FormaDepart::with(['formateur', 'departement'])->findOrFail($id);
        return response()->json($relation);
    }

    // Met à jour une relation
    public function update(Request $request, $id)
    {
        $relation = FormaDepart::findOrFail($id);

        $validated = $request->validate([
            'formateur_id' => 'sometimes|exists:formateurs,id',
            'departement_id' => 'sometimes|exists:departements,id',
        ]);

        $relation->update($validated);
        return response()->json($relation);
    }

    // Supprime une relation
    public function destroy($id)
    {
        $relation = FormaDepart::findOrFail($id);
        $relation->delete();
        return response()->json(['message' => 'Relation supprimée avec succès']);
    }
}
