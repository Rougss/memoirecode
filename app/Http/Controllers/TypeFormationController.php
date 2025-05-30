<?php

namespace App\Http\Controllers;

use App\Models\TypeFormation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class TypeFormationController extends Controller
{
    /**
     * Afficher la liste de tous les types de formation
     */
    public function index(): JsonResponse
    {
        try {
            $typeFormations = TypeFormation::all();
            return response()->json([
                'success' => true,
                'data' => $typeFormations,
                'message' => 'Types de formation récupérés avec succès'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des types de formation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer un nouveau type de formation
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validatedData = $request->validate([
                'intitule' => 'required|string|max:255|unique:type_formations,intitule'
            ], [
                'intitule.required' => 'L\'intitulé est obligatoire',
                'intitule.string' => 'L\'intitulé doit être une chaîne de caractères',
                'intitule.max' => 'L\'intitulé ne peut pas dépasser 255 caractères',
                'intitule.unique' => 'Cet intitulé existe déjà'
            ]);

            $typeFormation = TypeFormation::create($validatedData);

            return response()->json([
                'success' => true,
                'data' => $typeFormation,
                'message' => 'Type de formation créé avec succès'
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du type de formation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Afficher un type de formation spécifique
     */
    public function show(string $id): JsonResponse
    {
        try {
            $typeFormation = TypeFormation::with('metiers')->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $typeFormation,
                'message' => 'Type de formation trouvé'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Type de formation non trouvé'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du type de formation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour un type de formation
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $typeFormation = TypeFormation::findOrFail($id);

            $validatedData = $request->validate([
                'intitule' => 'required|string|max:255|unique:type_formations,intitule,' . $id
            ], [
                'intitule.required' => 'L\'intitulé est obligatoire',
                'intitule.string' => 'L\'intitulé doit être une chaîne de caractères',
                'intitule.max' => 'L\'intitulé ne peut pas dépasser 255 caractères',
                'intitule.unique' => 'Cet intitulé existe déjà'
            ]);

            $typeFormation->update($validatedData);

            return response()->json([
                'success' => true,
                'data' => $typeFormation,
                'message' => 'Type de formation mis à jour avec succès'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Type de formation non trouvé'
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du type de formation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprimer un type de formation
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $typeFormation = TypeFormation::findOrFail($id);
            
            // Vérifier s'il y a des métiers associés
            if ($typeFormation->metiers()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer ce type de formation car il est associé à des métiers'
                ], 409);
            }

            $typeFormation->delete();

            return response()->json([
                'success' => true,
                'message' => 'Type de formation supprimé avec succès'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Type de formation non trouvé'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du type de formation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}