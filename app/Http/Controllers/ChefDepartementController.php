<?php

namespace App\Http\Controllers;

use App\Models\Chef_Departement;
use App\Models\User;
use Illuminate\Http\Request;

class ChefDepartementController extends Controller
{
    // Créer un chef de département
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $chef = Chef_Departement::create([
            'user_id' => $request->user_id,
        ]);

        // Charger avec la relation user
        $chef = Chef_Departement::with('user')->find($chef->id);

        return response()->json([
            'message' => 'Chef de département créé avec succès.',
            'data' => $chef
        ], 201);
    }

    // Lister tous les chefs de département
    public function index()
    {
        return Chef_Departement::with('user')->get();
    }

    // Afficher un chef de département par ID
    public function show($id)
    {
        $chef = Chef_Departement::with('user')->findOrFail($id);
        return response()->json($chef);
    }

    // Supprimer un chef de département
    public function destroy($id)
    {
        $chef = Chef_Departement::findOrFail($id);
        $chef->delete();

        return response()->json([
            'message' => 'Chef de département supprimé avec succès.'
        ]);
    }
}
