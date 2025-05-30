<?php

namespace App\Http\Controllers;

use App\Models\Competence;
use Illuminate\Http\Request;

class CompetenceController extends Controller
{
    // Récupérer toutes les compétences
    public function index()
    {
        return Competence::all();
    }

    // Créer une nouvelle compétence
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'code' => 'required|string|max:255',
            'numero_competence' => 'required|string|max:255',
            'quota_horaire' => 'nullable|numeric',
            'metier_id' => 'required|exists:metiers,id',
            'formateur_id' => 'required|exists:formateurs,id',
        ]);

        $competence = Competence::create($validated);

        return response()->json($competence, 201);
    }

    // Afficher une compétence par id
    public function show($id)
    {
        $competence = Competence::find($id);

        if (!$competence) {
            return response()->json(['message' => 'Compétence non trouvée'], 404);
        }

        return response()->json($competence);
    }

    // Mettre à jour une compétence
    public function update(Request $request, $id)
    {
        $competence = Competence::find($id);

        if (!$competence) {
            return response()->json(['message' => 'Compétence non trouvée'], 404);
        }

        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'code' => 'required|string|max:255',
            'numero_competence' => 'required|string|max:255',
            'quota_horaire' => 'nullable|numeric',
            'metier_id' => 'required|exists:metiers,id',
            'formateur_id' => 'required|exists:formateurs,id',
        ]);

        $competence->update($validated);

        return response()->json($competence);
    }

    // Supprimer une compétence
    public function destroy($id)
    {
        $competence = Competence::find($id);

        if (!$competence) {
            return response()->json(['message' => 'Compétence non trouvée'], 404);
        }

        $competence->delete();

        return response()->json(['message' => 'Compétence supprimée avec succès']);
    }
}
