<?php

namespace App\Http\Controllers;

use App\Models\Role;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * Liste tous les rôles
     */
    public function index()
    {
        $roles = Role::all();

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    /**
     * Affiche un rôle par ID
     */
    public function show($id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Rôle non trouvé',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $role,
        ]);
    }

    /**
     * Crée un nouveau rôle
     */
    public function store(Request $request)
    {
        $request->validate([
            'intitule' => 'required|string|unique:roles,intitule',
            'permissions' => 'nullable|json',
        ]);

        $role = Role::create([
            'intitule' => $request->intitule,
            'permissions' => $request->permissions,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rôle créé avec succès',
            'data' => $role,
        ], 201);
    }

    /**
     * Met à jour un rôle existant
     */
    public function update(Request $request, $id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Rôle non trouvé',
            ], 404);
        }

        $request->validate([
            'intitule' => 'required|string|unique:roles,intitule,' . $id,
            'permissions' => 'nullable|json',
        ]);

        $role->intitule = $request->intitule;
        $role->permissions = $request->permissions;
        $role->save();

        return response()->json([
            'success' => true,
            'message' => 'Rôle mis à jour avec succès',
            'data' => $role,
        ]);
    }

    /**
     * Supprime un rôle
     */
    public function destroy($id)
    {
        $role = Role::find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Rôle non trouvé',
            ], 404);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Rôle supprimé avec succès',
        ]);
    }
}
