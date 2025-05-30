<?php

namespace App\Http\Controllers;

use App\Models\DirecteurDesEtude;
use App\Models\User;
use Illuminate\Http\Request;

class DirecteurDesEtudeController extends Controller
{
    // Création d’un directeur des études
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $directeur = DirecteurDesEtude::create([
            'user_id' => $request->user_id,
        ]);
        
        // Recharge avec la relation "user"
        $directeur = DirecteurDesEtude::with('user')->find($directeur->id);
        
        return response()->json([
            'message' => 'Directeur des études créé avec succès.',
            'data' => $directeur
        ], 201);
        
    }

    // Liste tous les directeurs des études
    public function index()
    {
        return DirecteurDesEtude::with('user')->get();
    }

    // Affiche un directeur des études spécifique
    public function show($id)
    {
        $directeur = DirecteurDesEtude::with('user')->findOrFail($id);
        return response()->json($directeur);
    }

    // Suppression d’un directeur des études
    public function destroy($id)
    {
        $directeur = DirecteurDesEtude::findOrFail($id);
        $directeur->delete();

        return response()->json([
            'message' => 'Directeur des études supprimé avec succès.'
        ]);
    }
}
