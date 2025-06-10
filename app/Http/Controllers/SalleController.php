<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Salle;

class SalleController extends Controller
{
    // Récupérer toutes les salles
    public function index()
    {
        // Charge aussi le bâtiment lié pour éviter la requête N+1
        return Salle::with('batiments')->get();
    }

    // Créer une nouvelle salle
    public function store(Request $request)
    {
        $validated = $request->validate([
            'intitule' => 'required|string|max:255',
            'nombre_de_place' => 'required|integer|min:1',
            'batiment_id' => 'required|exists:batiments,id',
        ]);

        $salle = Salle::create($validated);

        return response()->json($salle, 201);
    }

    // Afficher une salle précise
    public function show($id)
    {
        $salle = Salle::with('batiment')->find($id);

        if (!$salle) {
            return response()->json(['message' => 'Salle non trouvée'], 404);
        }

        return $salle;
    }

    // Mettre à jour une salle
    public function update(Request $request, $id)
    {
        $salle = Salle::find($id);

        if (!$salle) {
            return response()->json(['message' => 'Salle non trouvée'], 404);
        }

        $validated = $request->validate([
            'intitule' => 'sometimes|required|string|max:255',
            'nombre_de_place' => 'sometimes|required|integer|min:1',
            'batiment_id' => 'sometimes|required|exists:batiments,id',
        ]);

        $salle->update($validated);

        return $salle;
    }

    // Supprimer une salle
    public function destroy($id)
    {
        $salle = Salle::find($id);

        if (!$salle) {
            return response()->json(['message' => 'Salle non trouvée'], 404);
        }

        $salle->delete();

        return response()->json(['message' => 'Salle supprimée'], 200);
    }
}
