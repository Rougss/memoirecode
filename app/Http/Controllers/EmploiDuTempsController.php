<?php

namespace App\Http\Controllers;

use App\Models\EmploiDuTemps;
use Illuminate\Http\Request;

class EmploiDuTempsController extends Controller
{
    // Liste tous les créneaux
    public function index()
    {
        $creneaux = EmploiDuTemps::with(['annee', 'salle', 'competence'])->get();
        return response()->json(['success' => true, 'data' => $creneaux]);
    }

    // Créer un créneau
    public function store(Request $request)
    {
        $validated = $request->validate([
            'annee_id' => 'required|exists:annees,id',
            'heure_debut' => 'required|date_format:H:i:s',
            'heure_fin' => 'required|date_format:H:i:s|after:heure_debut',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
            'salle_id' => 'required|exists:salles,id',
            'competence_id' => 'required|exists:competences,id',
        ]);

        $creneau = EmploiDuTemps::create($validated);

        return response()->json(['success' => true, 'message' => 'Créneau créé', 'data' => $creneau]);
    }

    // Affiche un créneau précis
    public function show($id)
    {
        $creneau = EmploiDuTemps::with(['annee', 'salle', 'competence'])->find($id);

        if (!$creneau) {
            return response()->json(['success' => false, 'message' => 'Créneau non trouvé'], 404);
        }

        return response()->json(['success' => true, 'data' => $creneau]);
    }

    // Modifier un créneau
    public function update(Request $request, $id)
    {
        $creneau = EmploiDuTemps::find($id);

        if (!$creneau) {
            return response()->json(['success' => false, 'message' => 'Créneau non trouvé'], 404);
        }

        $validated = $request->validate([
            'annee_id' => 'sometimes|exists:annees,id',
            'heure_debut' => 'sometimes|date_format:H:i:s',
            'heure_fin' => 'sometimes|date_format:H:i:s|after:heure_debut',
            'date_debut' => 'sometimes|date',
            'date_fin' => 'sometimes|date|after_or_equal:date_debut',
            'salle_id' => 'sometimes|exists:salles,id',
            'competence_id' => 'sometimes|exists:competences,id',
        ]);

        $creneau->update($validated);

        return response()->json(['success' => true, 'message' => 'Créneau modifié', 'data' => $creneau]);
    }

    // Supprimer un créneau
    public function destroy($id)
    {
        $creneau = EmploiDuTemps::find($id);

        if (!$creneau) {
            return response()->json(['success' => false, 'message' => 'Créneau non trouvé'], 404);
        }

        $creneau->delete();

        return response()->json(['success' => true, 'message' => 'Créneau supprimé']);
    }
}
