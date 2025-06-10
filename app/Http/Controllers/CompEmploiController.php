<?php

namespace App\Http\Controllers;

use App\Models\CompEmploi;
use Illuminate\Http\Request;

class CompEmploiController extends Controller
{
    // Lister tous les éléments avec les relations
    public function index()
    {
        $compEmplois = CompEmploi::with(['emploiDuTemps', 'competence'])->get();
        return response()->json($compEmplois);
    }

    // Créer un nouvel élément
    public function store(Request $request)
    {
        $validated = $request->validate([
            'emploi_du_temps_id' => 'required|exists:emploi_du_temps,id',
            'competence_id' => 'required|exists:competences,id',
        ]);

        $compEmploi = CompEmploi::create($validated);
        $compEmploi->load(['emploiDuTemps', 'competence']);

        return response()->json($compEmploi, 201);
    }

    // Afficher un seul élément
    public function show($id)
    {
        $compEmploi = CompEmploi::with(['emploiDuTemps', 'competence'])->findOrFail($id);
        return response()->json($compEmploi);
    }

    // Mettre à jour un élément
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'emploi_du_temps_id' => 'required|exists:emploi_du_temps,id',
            'competence_id' => 'required|exists:competences,id',
        ]);

        $compEmploi = CompEmploi::findOrFail($id);
        $compEmploi->update($validated);
        $compEmploi->load(['emploiDuTemps', 'competence']);

        return response()->json($compEmploi);
    }

    // Supprimer un élément
    public function destroy($id)
    {
        $compEmploi = CompEmploi::findOrFail($id);
        $compEmploi->delete();

        return response()->json(['message' => 'Supprimé avec succès']);
    }
}
