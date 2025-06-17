<?php

namespace App\Http\Controllers;

use App\Models\Departement;
use Illuminate\Http\Request;

class DepartementController extends Controller
{
    // Lister tous les départements
    public function index()
    {
        return Departement::with('metiers', 'batiment')->get();
    }

    // Créer un nouveau département
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom_departement' => 'required|string|max:255|unique:departements,nom_departement',
            'batiment_id' => 'required|exists:batiments,id',
            'user_id' => 'required|exists:users,id',
            'formateur_id' => 'required|exists:formateurs,id'
        ]);

        $departement = Departement::create($validated);
        return response()->json($departement, 201);
    }

    // Afficher un département par son ID
    public function show($id)
    {
        $departement = Departement::with('metiers', 'batiment')->find($id);

        if (!$departement) {
            return response()->json(['message' => 'Département non trouvé'], 404);
        }

        return response()->json($departement);
    }

    // Mettre à jour un département
    public function update(Request $request, $id)
    {
        $departement = Departement::find($id);

        if (!$departement) {
            return response()->json(['message' => 'Département non trouvé'], 404);
        }

        $validated = $request->validate([
            'nom_departement' => 'required|string|max:255|unique:departements,nom_departement,' . $id,
            'batiment_id' => 'required|exists:batiments,id',
            'user_id' => 'required|exists:users,id',
            'formateur_id' => 'required|exists:formateurs,id'
        ]);

        $departement->update($validated);

        return response()->json($departement);
    }

    // Supprimer un département
    public function destroy($id)
    {
        $departement = Departement::find($id);

        if (!$departement) {
            return response()->json(['message' => 'Département non trouvé'], 404);
        }

        $departement->delete();

        return response()->json(['message' => 'Département supprimé avec succès']);
    }

    public function assignerChef(Request $request, $departementId)
{
    $validated = $request->validate([
        'formateur_id' => 'required|exists:formateurs,id'
    ]);

    $departement = Departement::findOrFail($departementId);
    
    $departement->update([
        'formateur_id' => $validated['formateur_id']
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Chef de département assigné avec succès',
        'data' => $departement->load('chefDepartement.user')
    ]);
}
}
