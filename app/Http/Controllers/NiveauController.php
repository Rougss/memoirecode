<?php

namespace App\Http\Controllers;

use App\Models\Niveau;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class NiveauController extends Controller
{
    /**
     * Liste tous les niveaux
     */
    public function index(): JsonResponse
    {
        try {
            $niveaux = Niveau::with('typeFormation')->get();
            return response()->json([
                'success' => true,
                'data' => $niveaux,
                'message' => 'Niveaux récupérés avec succès'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des niveaux',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Créer un nouveau niveau
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'intitule' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('niveaux')->where(function ($query) use ($request) {
                        return $query->where('type_formation_id', $request->type_formation_id);
                    })
                ],
                'type_formation_id' => 'required|exists:type_formations,id',
            ], [
                'intitule.required' => 'L\'intitulé est obligatoire',
                'intitule.string' => 'L\'intitulé doit être une chaîne de caractères',
                'intitule.max' => 'L\'intitulé ne peut pas dépasser 255 caractères',
                'intitule.unique' => 'Ce niveau existe déjà pour ce type de formation',
                'type_formation_id.required' => 'Le type de formation est obligatoire',
                'type_formation_id.exists' => 'Le type de formation sélectionné n\'existe pas'
            ]);

            $niveau = Niveau::create($validated);

            // Charger la relation pour la réponse
            $niveau->load('typeFormation');

            return response()->json([
                'success' => true,
                'data' => $niveau,
                'message' => 'Niveau créé avec succès'
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
                'message' => 'Erreur lors de la création du niveau',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Affiche un niveau spécifique
     */
    public function show(string $id): JsonResponse
    {
        try {
            $niveau = Niveau::with(['typeFormation', 'metiers'])->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $niveau,
                'message' => 'Niveau trouvé'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Niveau non trouvé'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du niveau',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Met à jour un niveau
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $niveau = Niveau::findOrFail($id);

            $validated = $request->validate([
                'intitule' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('niveaux')->where(function ($query) use ($request) {
                        return $query->where('type_formation_id', $request->type_formation_id);
                    })->ignore($id)
                ],
                'type_formation_id' => 'required|exists:type_formations,id',
            ], [
                'intitule.required' => 'L\'intitulé est obligatoire',
                'intitule.string' => 'L\'intitulé doit être une chaîne de caractères',
                'intitule.max' => 'L\'intitulé ne peut pas dépasser 255 caractères',
                'intitule.unique' => 'Ce niveau existe déjà pour ce type de formation',
                'type_formation_id.required' => 'Le type de formation est obligatoire',
                'type_formation_id.exists' => 'Le type de formation sélectionné n\'existe pas'
            ]);

            $niveau->update($validated);

            // Recharger la relation pour la réponse
            $niveau->load('typeFormation');

            return response()->json([
                'success' => true,
                'data' => $niveau,
                'message' => 'Niveau mis à jour avec succès'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Niveau non trouvé'
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
                'message' => 'Erreur lors de la mise à jour du niveau',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Supprime un niveau
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $niveau = Niveau::findOrFail($id);
            
            // Vérifier s'il y a des métiers associés
            if ($niveau->metiers()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Impossible de supprimer ce niveau car il est associé à des métiers'
                ], 409);
            }

            $niveau->delete();

            return response()->json([
                'success' => true,
                'message' => 'Niveau supprimé avec succès'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Niveau non trouvé'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du niveau',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir tous les niveaux d'un type de formation spécifique
     */
    public function getNiveauxByTypeFormation(int $typeFormationId): JsonResponse
    {
        try {
            $niveaux = Niveau::where('type_formation_id', $typeFormationId)
                            ->with('typeFormation')
                            ->orderBy('intitule')
                            ->get();

            return response()->json([
                'success' => true,
                'data' => $niveaux,
                'message' => 'Niveaux récupérés avec succès'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des niveaux',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les statistiques des niveaux
     */
    public function getStats(): JsonResponse
    {
        try {
            $totalNiveaux = Niveau::count();
            $niveauxParType = Niveau::with('typeFormation')
                                   ->get()
                                   ->groupBy('type_formation_id')
                                   ->map(function ($niveaux) {
                                       return [
                                           'type_formation' => $niveaux->first()->typeFormation->intitule ?? 'Aucun type',
                                           'count' => $niveaux->count(),
                                           'niveaux' => $niveaux->pluck('intitule')->toArray()
                                       ];
                                   });

            $niveauxSansType = Niveau::whereNull('type_formation_id')->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_niveaux' => $totalNiveaux,
                    'niveaux_par_type' => $niveauxParType,
                    'niveaux_sans_type' => $niveauxSansType,
                ],
                'message' => 'Statistiques récupérées avec succès'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}