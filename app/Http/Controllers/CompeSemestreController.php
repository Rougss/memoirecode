<?php

namespace App\Http\Controllers;

use App\Models\CompeSemestre;
use Illuminate\Http\Request;

class CompeSemestreController extends Controller
{
    // Lister tous les éléments
    public function index()
    {
        $compeSemestres = CompeSemestre::with(['competence', 'semestre'])->get();
        return response()->json($compeSemestres);
    }

    // Créer un nouvel élément
    public function store(Request $request)
    {
        $validated = $request->validate([
            'competence_id' => 'required|exists:competences,id',
            'semestre_id' => 'required|exists:semestres,id',
        ]);

        $compeSemestre = CompeSemestre::create($validated);

        // Charger les relations avant de retourner
        $compeSemestre->load(['competence', 'semestre']);

        return response()->json($compeSemestre, 201);
    }

    // Afficher un seul élément
    public function show($id)
    {
        $compeSemestre = CompeSemestre::with(['competence', 'semestre'])->findOrFail($id);
        return response()->json($compeSemestre);
    }

    // Mettre à jour un élément
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'competence_id' => 'required|exists:competences,id',
            'semestre_id' => 'required|exists:semestres,id',
        ]);

        $compeSemestre = CompeSemestre::findOrFail($id);
        $compeSemestre->update($validated);

        // Recharger les relations après mise à jour
        $compeSemestre->load(['competence', 'semestre']);

        return response()->json($compeSemestre);
    }

    // Supprimer un élément
    public function destroy($id)
    {
        $compeSemestre = CompeSemestre::findOrFail($id);
        $compeSemestre->delete();

        return response()->json(['message' => 'Supprimé avec succès']);
    }
}
