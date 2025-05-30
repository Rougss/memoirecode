<?php

namespace App\Http\Controllers;
use App\Models\Specialite;

use Illuminate\Http\Request;

class SpecialiteController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => Specialite::all()
        ]);
    }
    public function store (Request $request)
    {
        $request->validate([
            'intitule' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $specialite = Specialite::create($request->all());

        return response()->json([
            'success' => true,
            'data' => $specialite
        ], 201);
    }
    public function show($id)
    {
        $specialite = Specialite::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $specialite
        ]);
    }
    public function update(Request $request, $id)
    {
        $specialite = Specialite::findOrFail($id);

        $request->validate([
            'intitule' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $specialite->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $specialite
        ]);
    }
    public function destroy($id)
    {
        $specialite = Specialite::findOrFail($id);
        $specialite->delete();

        return response()->json([
            'success' => true,
            'message' => 'Specialite deleted successfully'
        ]);
    }
}
