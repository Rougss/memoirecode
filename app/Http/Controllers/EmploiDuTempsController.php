<?php

namespace App\Http\Controllers;

use App\Models\EmploiDuTemps;
use App\Models\Formateur;
use App\Models\Departement;
use App\Models\Salle;
use App\Models\Annee;
use App\Models\Competence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class EmploiDuTempsController extends Controller
{
    // =============================================
    // MIDDLEWARE ET VÉRIFICATIONS CHEF DÉPARTEMENT
    // =============================================

    /**
     * Vérifier si l'utilisateur connecté est chef du département
     */
    private function verifierChefDepartement($departement_id = null)
    {
        $user = Auth::user();
        $formateur = Formateur::where('user_id', $user->id)->first();

        if (!$formateur) {
            return false;
        }

        // Si département spécifique fourni
        if ($departement_id) {
            $departement = Departement::find($departement_id);
            return $departement && $departement->formateur_id == $formateur->id;
        }

        // Vérifier si le formateur est chef d'au moins un département
        return Departement::where('formateur_id', $formateur->id)->exists();
    }

    /**
     * Obtenir les départements gérés par le formateur connecté
     */
    private function getDepartementsGeres()
    {
        $user = Auth::user();
        $formateur = Formateur::where('user_id', $user->id)->first();

        if (!$formateur) {
            return collect();
        }

        return Departement::where('formateur_id', $formateur->id)->get();
    }

    // =============================================
    // MÉTHODES CRUD AVEC CONTRÔLE D'ACCÈS
    // =============================================

    /**
     * Afficher la liste des emplois du temps (chef seulement)
     */
    public function index(Request $request)
    {
        if (!$this->verifierChefDepartement()) {
            return response()->json([
                'success' => false, 
                'message' => 'Accès refusé. Seuls les chefs de département peuvent voir les emplois du temps.'
            ], 403);
        }

        $departementsGeres = $this->getDepartementsGeres();
        $departementIds = $departementsGeres->pluck('id');

        // Récupérer les années des départements gérés
        $annees = Annee::whereIn('departement_id', $departementIds)->get();
        $anneeIds = $annees->pluck('id');

        $query = EmploiDuTemps::with(['annee.departement', 'formateur.user', 'salle', 'competence'])
            ->whereIn('annee_id', $anneeIds);

        // Filtres optionnels
        if ($request->has('departement_id')) {
            $query->whereHas('annee', function($q) use ($request) {
                $q->where('departement_id', $request->departement_id);
            });
        }

        if ($request->has('date_debut') && $request->has('date_fin')) {
            $query->whereBetween('date_debut', [$request->date_debut, $request->date_fin]);
        }

        $creneaux = $query->orderBy('date_debut')->orderBy('heure_debut')->get();

        return response()->json([
            'success' => true, 
            'data' => $creneaux,
            'departements_geres' => $departementsGeres,
            'formateur_chef' => Auth::user()->nom . ' ' . Auth::user()->prenom
        ]);
    }

    /**
     * Créer un nouveau créneau (chef seulement)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'annee_id' => 'required|exists:annees,id',
            'formateur_id' => 'required|exists:formateurs,id',
            'salle_id' => 'required|exists:salles,id',
            'competence_id' => 'nullable|exists:competences,id',
            'heure_debut' => 'required|date_format:H:i:s',
            'heure_fin' => 'required|date_format:H:i:s|after:heure_debut',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
        ]);

        // Vérifier que l'année appartient à un département géré par ce chef
        $annee = Annee::with('departement')->find($validated['annee_id']);
        
        if (!$this->verifierChefDepartement($annee->departement_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez créer des créneaux que pour vos départements.'
            ], 403);
        }

        // Vérifier les conflits avant création
        $conflits = $this->verifierConflitsCreation($validated);
        if (!empty($conflits)) {
            return response()->json([
                'success' => false,
                'message' => 'Conflits détectés',
                'conflicts' => $conflits
            ], 422);
        }

        $creneau = EmploiDuTemps::create($validated);
        
        return response()->json([
            'success' => true, 
            'message' => 'Créneau créé avec succès', 
            'data' => $creneau->load(['annee', 'formateur.user', 'salle', 'competence'])
        ]);
    }

    /**
     * Afficher un créneau spécifique
     */
    public function show($id)
    {
        $creneau = EmploiDuTemps::with(['annee.departement', 'formateur.user', 'salle', 'competence'])->find($id);
        
        if (!$creneau) {
            return response()->json(['success' => false, 'message' => 'Créneau non trouvé'], 404);
        }

        // Vérifier que le créneau appartient à un département géré
        if (!$this->verifierChefDepartement($creneau->annee->departement_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé à ce créneau.'
            ], 403);
        }

        return response()->json(['success' => true, 'data' => $creneau]);
    }

    /**
     * Modifier un créneau (chef seulement)
     */
    public function update(Request $request, $id)
    {
        $creneau = EmploiDuTemps::with('annee.departement')->find($id);
        
        if (!$creneau) {
            return response()->json(['success' => false, 'message' => 'Créneau non trouvé'], 404);
        }

        // Vérifier que le chef peut modifier ce créneau
        if (!$this->verifierChefDepartement($creneau->annee->departement_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez modifier que les créneaux de vos départements.'
            ], 403);
        }

        $validated = $request->validate([
            'annee_id' => 'sometimes|exists:annees,id',
            'formateur_id' => 'sometimes|exists:formateurs,id',
            'salle_id' => 'sometimes|exists:salles,id',
            'competence_id' => 'nullable|exists:competences,id',
            'heure_debut' => 'sometimes|date_format:H:i:s',
            'heure_fin' => 'sometimes|date_format:H:i:s|after:heure_debut',
            'date_debut' => 'sometimes|date',
            'date_fin' => 'sometimes|date|after_or_equal:date_debut',
        ]);

        // Si changement d'année, vérifier le nouveau département
        if (isset($validated['annee_id'])) {
            $nouvelleAnnee = Annee::with('departement')->find($validated['annee_id']);
            if (!$this->verifierChefDepartement($nouvelleAnnee->departement_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'La nouvelle année doit appartenir à un de vos départements.'
                ], 403);
            }
        }

        $creneau->update($validated);
        
        return response()->json([
            'success' => true, 
            'message' => 'Créneau modifié avec succès', 
            'data' => $creneau->load(['annee', 'formateur.user', 'salle', 'competence'])
        ]);
    }

    /**
     * Supprimer un créneau (chef seulement)
     */
    public function destroy($id)
    {
        $creneau = EmploiDuTemps::with('annee.departement')->find($id);
        
        if (!$creneau) {
            return response()->json(['success' => false, 'message' => 'Créneau non trouvé'], 404);
        }

        // Vérifier que le chef peut supprimer ce créneau
        if (!$this->verifierChefDepartement($creneau->annee->departement_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez supprimer que les créneaux de vos départements.'
            ], 403);
        }

        $creneau->delete();
        
        return response()->json([
            'success' => true, 
            'message' => 'Créneau supprimé avec succès'
        ]);
    }

    // =============================================
    // GÉNÉRATION AUTOMATIQUE D'EMPLOI DU TEMPS
    // =============================================

    // Créneaux horaires fixes
    private $timeSlots = [
        ['debut' => '08:00:00', 'fin' => '10:00:00'],
        ['debut' => '10:15:00', 'fin' => '12:15:00'],
        ['debut' => '14:00:00', 'fin' => '16:00:00'],
        ['debut' => '16:15:00', 'fin' => '18:15:00'],
    ];

    /**
     * Génère automatiquement l'emploi du temps (chef seulement)
     */
    public function generateSchedule(Request $request)
    {
        if (!$this->verifierChefDepartement()) {
            return response()->json([
                'success' => false,
                'message' => 'Seuls les chefs de département peuvent générer des emplois du temps.'
            ], 403);
        }

        $validated = $request->validate([
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after:date_debut',
            'departement_id' => 'required|exists:departements,id'
        ]);

        // Vérifier que le chef gère ce département
        if (!$this->verifierChefDepartement($validated['departement_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez générer des emplois du temps que pour vos départements.'
            ], 403);
        }

        try {
            $nouveauxCreneaux = [];
            
            // Récupérer les données du département
            $departement = Departement::find($validated['departement_id']);
            $annees = Annee::where('departement_id', $departement->id)->get();
            $salles = Salle::where('id_batiment', $departement->batiment_id)->get();
            $formateurs = Formateur::where('departement_id', $departement->id)
                                  ->with('competences')->get();

            if ($annees->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucune année trouvée pour ce département.'
                ], 400);
            }

            // Générer pour chaque jour de la période
            $currentDate = Carbon::parse($validated['date_debut']);
            $endDate = Carbon::parse($validated['date_fin']);

            while ($currentDate->lte($endDate)) {
                // Ignorer les week-ends
                if (!$currentDate->isWeekend()) {
                    $creneauxDuJour = $this->genererCreneauxPourUnJour(
                        $currentDate->toDateString(),
                        $annees,
                        $salles,
                        $formateurs
                    );
                    $nouveauxCreneaux = array_merge($nouveauxCreneaux, $creneauxDuJour);
                }
                $currentDate->addDay();
            }

            // Sauvegarder
            foreach ($nouveauxCreneaux as $creneau) {
                EmploiDuTemps::create($creneau);
            }

            return response()->json([
                'success' => true,
                'message' => count($nouveauxCreneaux) . ' créneaux générés automatiquement pour le département ' . $departement->nom_departement,
                'data' => $nouveauxCreneaux,
                'departement' => $departement->nom_departement
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération: ' . $e->getMessage()
            ], 500);
        }
    }

    // =============================================
    // CONSULTATION DES EMPLOIS DU TEMPS
    // =============================================

    /**
     * Emploi du temps d'un formateur
     */
    public function getFormateurSchedule($formateurId, Request $request)
    {
        $validated = $request->validate([
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
        ]);

        $formateur = Formateur::with('user', 'departement')->find($formateurId);
        if (!$formateur) {
            return response()->json(['success' => false, 'message' => 'Formateur non trouvé'], 404);
        }

        $emploiDuTemps = EmploiDuTemps::with(['annee.departement', 'salle', 'competence'])
            ->where('formateur_id', $formateurId)
            ->whereBetween('date_debut', [$validated['date_debut'], $validated['date_fin']])
            ->orderBy('date_debut')
            ->orderBy('heure_debut')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $emploiDuTemps,
            'formateur' => $formateur,
            'periode' => [
                'debut' => $validated['date_debut'],
                'fin' => $validated['date_fin']
            ]
        ]);
    }

    /**
     * Emploi du temps d'une année
     */
    public function getAnneeSchedule($anneeId, Request $request)
    {
        $validated = $request->validate([
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
        ]);

        $annee = Annee::with('departement')->find($anneeId);
        if (!$annee) {
            return response()->json(['success' => false, 'message' => 'Année non trouvée'], 404);
        }

        $emploiDuTemps = EmploiDuTemps::with(['formateur.user', 'salle', 'competence'])
            ->where('annee_id', $anneeId)
            ->whereBetween('date_debut', [$validated['date_debut'], $validated['date_fin']])
            ->orderBy('date_debut')
            ->orderBy('heure_debut')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $emploiDuTemps,
            'annee' => $annee,
            'periode' => [
                'debut' => $validated['date_debut'],
                'fin' => $validated['date_fin']
            ]
        ]);
    }

    /**
     * Emploi du temps d'un département (chef seulement)
     */
    public function getDepartementSchedule($departementId, Request $request)
    {
        if (!$this->verifierChefDepartement($departementId)) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Vous ne gérez pas ce département.'
            ], 403);
        }

        $validated = $request->validate([
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
        ]);

        $departement = Departement::with('formateurs.user')->find($departementId);
        $annees = Annee::where('departement_id', $departementId)->get();
        $anneeIds = $annees->pluck('id');

        $emploiDuTemps = EmploiDuTemps::with(['annee', 'formateur.user', 'salle', 'competence'])
            ->whereIn('annee_id', $anneeIds)
            ->whereBetween('date_debut', [$validated['date_debut'], $validated['date_fin']])
            ->orderBy('date_debut')
            ->orderBy('heure_debut')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $emploiDuTemps,
            'departement' => $departement,
            'annees' => $annees,
            'periode' => [
                'debut' => $validated['date_debut'],
                'fin' => $validated['date_fin']
            ]
        ]);
    }

    // =============================================
    // VÉRIFICATION DES CONFLITS
    // =============================================

    /**
     * Vérifier les conflits dans les emplois du temps
     */
    public function checkConflicts(Request $request)
    {
        if (!$this->verifierChefDepartement()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé.'
            ], 403);
        }

        $validated = $request->validate([
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
            'departement_id' => 'nullable|exists:departements,id'
        ]);

        $conflicts = [];
        $departementsGeres = $this->getDepartementsGeres();

        // Si département spécifique
        if (isset($validated['departement_id'])) {
            if (!$this->verifierChefDepartement($validated['departement_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne gérez pas ce département.'
                ], 403);
            }
            $departementsGeres = $departementsGeres->where('id', $validated['departement_id']);
        }

        foreach ($departementsGeres as $dept) {
            $anneeIds = Annee::where('departement_id', $dept->id)->pluck('id');
            
            // Conflits formateurs
            $formateurConflicts = EmploiDuTemps::selectRaw('formateur_id, date_debut, heure_debut, heure_fin, COUNT(*) as count')
                ->whereIn('annee_id', $anneeIds)
                ->whereBetween('date_debut', [$validated['date_debut'], $validated['date_fin']])
                ->groupBy('formateur_id', 'date_debut', 'heure_debut', 'heure_fin')
                ->having('count', '>', 1)
                ->with('formateur.user')
                ->get();

            foreach ($formateurConflicts as $conflict) {
                $conflicts[] = [
                    'type' => 'formateur',
                    'departement' => $dept->nom_departement,
                    'message' => "Le formateur {$conflict->formateur->user->nom} {$conflict->formateur->user->prenom} a {$conflict->count} cours en même temps",
                    'date' => $conflict->date_debut,
                    'heure' => $conflict->heure_debut . ' - ' . $conflict->heure_fin
                ];
            }

            // Conflits salles
            $salleConflicts = EmploiDuTemps::selectRaw('salle_id, date_debut, heure_debut, heure_fin, COUNT(*) as count')
                ->whereIn('annee_id', $anneeIds)
                ->whereBetween('date_debut', [$validated['date_debut'], $validated['date_fin']])
                ->groupBy('salle_id', 'date_debut', 'heure_debut', 'heure_fin')
                ->having('count', '>', 1)
                ->with('salle')
                ->get();

            foreach ($salleConflicts as $conflict) {
                $conflicts[] = [
                    'type' => 'salle',
                    'departement' => $dept->nom_departement,
                    'message' => "La salle {$conflict->salle->intitule} a {$conflict->count} cours en même temps",
                    'date' => $conflict->date_debut,
                    'heure' => $conflict->heure_debut . ' - ' . $conflict->heure_fin
                ];
            }
        }

        return response()->json([
            'success' => true,
            'conflicts' => $conflicts,
            'has_conflicts' => count($conflicts) > 0,
            'departements_verifies' => $departementsGeres->pluck('nom_departement')
        ]);
    }

    // =============================================
    // MÉTHODES PRIVÉES POUR LA GÉNÉRATION
    // =============================================

    private function verifierConflitsCreation($data)
    {
        $conflicts = [];

        // Vérifier conflit formateur
        $conflitFormateur = EmploiDuTemps::where('formateur_id', $data['formateur_id'])
            ->where('date_debut', $data['date_debut'])
            ->where(function($query) use ($data) {
                $query->whereBetween('heure_debut', [$data['heure_debut'], $data['heure_fin']])
                      ->orWhereBetween('heure_fin', [$data['heure_debut'], $data['heure_fin']])
                      ->orWhere(function($q) use ($data) {
                          $q->where('heure_debut', '<=', $data['heure_debut'])
                            ->where('heure_fin', '>=', $data['heure_fin']);
                      });
            })
            ->exists();

        if ($conflitFormateur) {
            $conflicts[] = "Le formateur a déjà un cours à cette heure";
        }

        // Vérifier conflit salle
        $conflitSalle = EmploiDuTemps::where('salle_id', $data['salle_id'])
            ->where('date_debut', $data['date_debut'])
            ->where(function($query) use ($data) {
                $query->whereBetween('heure_debut', [$data['heure_debut'], $data['heure_fin']])
                      ->orWhereBetween('heure_fin', [$data['heure_debut'], $data['heure_fin']])
                      ->orWhere(function($q) use ($data) {
                          $q->where('heure_debut', '<=', $data['heure_debut'])
                            ->where('heure_fin', '>=', $data['heure_fin']);
                      });
            })
            ->exists();

        if ($conflitSalle) {
            $conflicts[] = "La salle est déjà occupée à cette heure";
        }

        // Vérifier conflit année
        $conflitAnnee = EmploiDuTemps::where('annee_id', $data['annee_id'])
            ->where('date_debut', $data['date_debut'])
            ->where(function($query) use ($data) {
                $query->whereBetween('heure_debut', [$data['heure_debut'], $data['heure_fin']])
                      ->orWhereBetween('heure_fin', [$data['heure_debut'], $data['heure_fin']])
                      ->orWhere(function($q) use ($data) {
                          $q->where('heure_debut', '<=', $data['heure_debut'])
                            ->where('heure_fin', '>=', $data['heure_fin']);
                      });
            })
            ->exists();

        if ($conflitAnnee) {
            $conflicts[] = "L'année a déjà un cours à cette heure";
        }

        return $conflicts;
    }

    private function genererCreneauxPourUnJour($date, $annees, $salles, $formateurs)
    {
        $creneauxDuJour = [];
        $ressourcesOccupees = $this->getRessourcesOccupees($date);

        foreach ($this->timeSlots as $horaire) {
            foreach ($annees as $annee) {
                // Vérifier si l'année est déjà occupée
                $cleAnnee = "{$date}_{$horaire['debut']}_{$horaire['fin']}_annee_{$annee->id}";
                if (isset($ressourcesOccupees[$cleAnnee])) {
                    continue;
                }

                // Trouver formateur disponible
                $formateurDisponible = $this->trouverFormateurDisponible(
                    $formateurs, 
                    $ressourcesOccupees, 
                    $date, 
                    $horaire
                );

                if (!$formateurDisponible) continue;

                // Trouver salle disponible
                $salleDisponible = $this->trouverSalleDisponible(
                    $salles, 
                    $ressourcesOccupees, 
                    $date, 
                    $horaire
                );

                if (!$salleDisponible) continue;

                // Choisir une compétence
                $competence = $formateurDisponible->competences->first();

                // Créer le créneau
                $nouveauCreneau = [
                    'annee_id' => $annee->id,
                    'formateur_id' => $formateurDisponible->id,
                    'salle_id' => $salleDisponible->id,
                    'competence_id' => $competence ? $competence->id : null,
                    'date_debut' => $date,
                    'date_fin' => $date,
                    'heure_debut' => $horaire['debut'],
                    'heure_fin' => $horaire['fin'],
                ];

                $creneauxDuJour[] = $nouveauCreneau;

                // Marquer comme occupé
                $ressourcesOccupees["{$date}_{$horaire['debut']}_{$horaire['fin']}_formateur_{$formateurDisponible->id}"] = true;
                $ressourcesOccupees["{$date}_{$horaire['debut']}_{$horaire['fin']}_salle_{$salleDisponible->id}"] = true;
                $ressourcesOccupees["{$date}_{$horaire['debut']}_{$horaire['fin']}_annee_{$annee->id}"] = true;

                break;
            }
        }

        return $creneauxDuJour;
    }

    private function getRessourcesOccupees($date)
    {
        $creneauxExistants = EmploiDuTemps::where('date_debut', $date)->get();
        $occupees = [];

        foreach ($creneauxExistants as $creneau) {
            $occupees["{$date}_{$creneau->heure_debut}_{$creneau->heure_fin}_formateur_{$creneau->formateur_id}"] = true;
            $occupees["{$date}_{$creneau->heure_debut}_{$creneau->heure_fin}_salle_{$creneau->salle_id}"] = true;
            $occupees["{$date}_{$creneau->heure_debut}_{$creneau->heure_fin}_annee_{$creneau->annee_id}"] = true;
        }

        return $occupees;
    }

    private function trouverFormateurDisponible($formateurs, $ressourcesOccupees, $date, $horaire)
    {
        foreach ($formateurs as $formateur) {
            $cle = "{$date}_{$horaire['debut']}_{$horaire['fin']}_formateur_{$formateur->id}";
            
            if (!isset($ressourcesOccupees[$cle]) && $formateur->competences->count() > 0) {
                return $formateur;
            }
        }
        return null;
    }

    private function trouverSalleDisponible($salles, $ressourcesOccupees, $date, $horaire)
    {
        foreach ($salles as $salle) {
            $cle = "{$date}_{$horaire['debut']}_{$horaire['fin']}_salle_{$salle->id}";
            
            if (!isset($ressourcesOccupees[$cle])) {
                return $salle;
            }
        }
        return null;
    }

    // =============================================
    // MÉTHODES UTILITAIRES
    // =============================================

    /**
     * Obtenir les informations du formateur connecté
     */
    public function getFormateurConnecte()
    {
        $user = Auth::user();
        $formateur = Formateur::with(['departement', 'departementsChef'])
                             ->where('user_id', $user->id)
                             ->first();

        if (!$formateur) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé en tant que formateur.'
            ], 404);
        }

        $estChef = $formateur->departementsChef->count() > 0;

        return response()->json([
            'success' => true,
            'data' => [
                'formateur' => $formateur,
                'user' => $user,
                'est_chef_departement' => $estChef,
                'departements_geres' => $estChef ? $formateur->departementsChef : [],
                'departement_rattache' => $formateur->departement
            ]
        ]);
    }

    /**
     * Obtenir les statistiques des emplois du temps (chef seulement)
     */
    public function getStatistiques(Request $request)
    {
        if (!$this->verifierChefDepartement()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Seuls les chefs de département peuvent consulter les statistiques.'
            ], 403);
        }

        $validated = $request->validate([
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
            'departement_id' => 'nullable|exists:departements,id'
        ]);

        $departementsGeres = $this->getDepartementsGeres();
        
        // Si département spécifique
        if (isset($validated['departement_id'])) {
            if (!$this->verifierChefDepartement($validated['departement_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne gérez pas ce département.'
                ], 403);
            }
            $departementsGeres = $departementsGeres->where('id', $validated['departement_id']);
        }

        $statistiques = [];

        foreach ($departementsGeres as $dept) {
            $anneeIds = Annee::where('departement_id', $dept->id)->pluck('id');
            
            // Nombre total de créneaux
            $totalCreneaux = EmploiDuTemps::whereIn('annee_id', $anneeIds)
                ->whereBetween('date_debut', [$validated['date_debut'], $validated['date_fin']])
                ->count();

            // Heures totales d'enseignement
            $heures = EmploiDuTemps::whereIn('annee_id', $anneeIds)
                ->whereBetween('date_debut', [$validated['date_debut'], $validated['date_fin']])
                ->selectRaw('TIME_TO_SEC(TIMEDIFF(heure_fin, heure_debut)) as duree_secondes')
                ->get()
                ->sum('duree_secondes');
            
            $heuresTotales = round($heures / 3600, 2);

            // Nombre de formateurs actifs
            $formateursActifs = EmploiDuTemps::whereIn('annee_id', $anneeIds)
                ->whereBetween('date_debut', [$validated['date_debut'], $validated['date_fin']])
                ->distinct('formateur_id')
                ->count('formateur_id');

            // Nombre de salles utilisées
            $sallesUtilisees = EmploiDuTemps::whereIn('annee_id', $anneeIds)
                ->whereBetween('date_debut', [$validated['date_debut'], $validated['date_fin']])
                ->distinct('salle_id')
                ->count('salle_id');

            // Répartition par années
            $repartitionAnnees = EmploiDuTemps::whereIn('annee_id', $anneeIds)
                ->whereBetween('date_debut', [$validated['date_debut'], $validated['date_fin']])
                ->selectRaw('annee_id, COUNT(*) as nombre_creneaux')
                ->groupBy('annee_id')
                ->with('annee')
                ->get();

            // Répartition par formateurs
            $repartitionFormateurs = EmploiDuTemps::whereIn('annee_id', $anneeIds)
                ->whereBetween('date_debut', [$validated['date_debut'], $validated['date_fin']])
                ->selectRaw('formateur_id, COUNT(*) as nombre_creneaux, SUM(TIME_TO_SEC(TIMEDIFF(heure_fin, heure_debut))) as heures_secondes')
                ->groupBy('formateur_id')
                ->with('formateur.user')
                ->get()
                ->map(function($item) {
                    $item->heures_totales = round($item->heures_secondes / 3600, 2);
                    return $item;
                });

            // Taux d'occupation des salles
            $sallesDisponibles = Salle::where('id_batiment', $dept->batiment_id)->count();
            $tauxOccupationSalles = $sallesDisponibles > 0 ? round(($sallesUtilisees / $sallesDisponibles) * 100, 2) : 0;

            $statistiques[] = [
                'departement' => $dept,
                'resume' => [
                    'total_creneaux' => $totalCreneaux,
                    'heures_totales' => $heuresTotales,
                    'formateurs_actifs' => $formateursActifs,
                    'salles_utilisees' => $sallesUtilisees,
                    'salles_disponibles' => $sallesDisponibles,
                    'taux_occupation_salles' => $tauxOccupationSalles
                ],
                'repartitions' => [
                    'par_annees' => $repartitionAnnees,
                    'par_formateurs' => $repartitionFormateurs
                ]
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $statistiques,
            'periode' => [
                'debut' => $validated['date_debut'],
                'fin' => $validated['date_fin']
            ],
            'departements_analyses' => $departementsGeres->count()
        ]);
    }

    /**
     * Exporter l'emploi du temps en PDF ou Excel (chef seulement)
     */
    public function exportSchedule(Request $request)
    {
        if (!$this->verifierChefDepartement()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé. Seuls les chefs de département peuvent exporter les emplois du temps.'
            ], 403);
        }

        $validated = $request->validate([
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
            'departement_id' => 'required|exists:departements,id',
            'format' => 'required|in:pdf,excel',
            'type' => 'required|in:departement,formateur,annee',
            'entity_id' => 'nullable|integer'
        ]);

        // Vérifier les permissions
        if (!$this->verifierChefDepartement($validated['departement_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez exporter que pour vos départements.'
            ], 403);
        }

        try {
            $donnees = $this->preparerDonneesExport($validated);
            
            if ($validated['format'] === 'pdf') {
                return $this->exporterPDF($donnees, $validated);
            } else {
                return $this->exporterExcel($donnees, $validated);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'export: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Dupliquer une semaine d'emploi du temps (chef seulement)
     */
    public function duplicateWeek(Request $request)
    {
        if (!$this->verifierChefDepartement()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé.'
            ], 403);
        }

        $validated = $request->validate([
            'semaine_source' => 'required|date',
            'semaine_destination' => 'required|date|different:semaine_source',
            'departement_id' => 'required|exists:departements,id'
        ]);

        // Vérifier les permissions
        if (!$this->verifierChefDepartement($validated['departement_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez dupliquer que pour vos départements.'
            ], 403);
        }

        try {
            // Calculer les dates de début et fin de la semaine source
            $dateSource = Carbon::parse($validated['semaine_source'])->startOfWeek();
            $finSource = $dateSource->copy()->endOfWeek();

            // Calculer les dates de la semaine destination
            $dateDestination = Carbon::parse($validated['semaine_destination'])->startOfWeek();
            $differenceSemaines = $dateSource->diffInWeeks($dateDestination, false);

            // Récupérer les créneaux de la semaine source
            $anneeIds = Annee::where('departement_id', $validated['departement_id'])->pluck('id');
            
            $creneauxSource = EmploiDuTemps::whereIn('annee_id', $anneeIds)
                ->whereBetween('date_debut', [$dateSource->toDateString(), $finSource->toDateString()])
                ->get();

            if ($creneauxSource->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Aucun créneau trouvé pour la semaine source.'
                ], 400);
            }

            // Supprimer les créneaux existants de la semaine destination (optionnel)
            $finDestination = $dateDestination->copy()->endOfWeek();
            EmploiDuTemps::whereIn('annee_id', $anneeIds)
                ->whereBetween('date_debut', [$dateDestination->toDateString(), $finDestination->toDateString()])
                ->delete();

            // Dupliquer les créneaux
            $nouveauxCreneaux = [];
            foreach ($creneauxSource as $creneau) {
                $nouvelleDateDebut = Carbon::parse($creneau->date_debut)->addWeeks($differenceSemaines);
                $nouvelleDateFin = Carbon::parse($creneau->date_fin)->addWeeks($differenceSemaines);

                $nouveauCreneau = $creneau->replicate();
                $nouveauCreneau->date_debut = $nouvelleDateDebut->toDateString();
                $nouveauCreneau->date_fin = $nouvelleDateFin->toDateString();
                
                // Vérifier les conflits avant création
                $donneesValidation = [
                    'annee_id' => $nouveauCreneau->annee_id,
                    'formateur_id' => $nouveauCreneau->formateur_id,
                    'salle_id' => $nouveauCreneau->salle_id,
                    'date_debut' => $nouveauCreneau->date_debut,
                    'heure_debut' => $nouveauCreneau->heure_debut,
                    'heure_fin' => $nouveauCreneau->heure_fin
                ];

                $conflits = $this->verifierConflitsCreation($donneesValidation);
                
                if (empty($conflits)) {
                    $nouveauCreneau->save();
                    $nouveauxCreneaux[] = $nouveauCreneau;
                }
            }

            return response()->json([
                'success' => true,
                'message' => count($nouveauxCreneaux) . ' créneaux dupliqués avec succès.',
                'data' => [
                    'creneaux_sources' => $creneauxSource->count(),
                    'creneaux_dupliques' => count($nouveauxCreneaux),
                    'semaine_source' => $dateSource->toDateString(),
                    'semaine_destination' => $dateDestination->toDateString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la duplication: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtenir les créneaux libres pour une date donnée
     */
    public function getCreneauxLibres(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'departement_id' => 'required|exists:departements,id'
        ]);

        // Vérifier les permissions si c'est un chef
        if ($this->verifierChefDepartement()) {
            if (!$this->verifierChefDepartement($validated['departement_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez consulter que vos départements.'
                ], 403);
            }
        }

        $departement = Departement::find($validated['departement_id']);
        $date = $validated['date'];

        // Récupérer toutes les ressources
        $annees = Annee::where('departement_id', $departement->id)->get();
        $formateurs = Formateur::where('departement_id', $departement->id)->get();
        $salles = Salle::where('id_batiment', $departement->batiment_id)->get();

        // Récupérer les créneaux occupés
        $creneauxOccupes = EmploiDuTemps::whereIn('annee_id', $annees->pluck('id'))
            ->where('date_debut', $date)
            ->get();

        $creneauxLibres = [];

        foreach ($this->timeSlots as $horaire) {
            // Vérifier pour chaque combinaison année/formateur/salle
            foreach ($annees as $annee) {
                $anneeOccupee = $creneauxOccupes->where('annee_id', $annee->id)
                    ->where('heure_debut', $horaire['debut'])
                    ->where('heure_fin', $horaire['fin'])
                    ->isNotEmpty();

                if (!$anneeOccupee) {
                    $formateursLibres = [];
                    $sallesLibres = [];

                    foreach ($formateurs as $formateur) {
                        $formateurOccupe = $creneauxOccupes->where('formateur_id', $formateur->id)
                            ->where('heure_debut', $horaire['debut'])
                            ->where('heure_fin', $horaire['fin'])
                            ->isNotEmpty();

                        if (!$formateurOccupe) {
                            $formateursLibres[] = $formateur;
                        }
                    }

                    foreach ($salles as $salle) {
                        $salleOccupee = $creneauxOccupes->where('salle_id', $salle->id)
                            ->where('heure_debut', $horaire['debut'])
                            ->where('heure_fin', $horaire['fin'])
                            ->isNotEmpty();

                        if (!$salleOccupee) {
                            $sallesLibres[] = $salle;
                        }
                    }

                    if (!empty($formateursLibres) && !empty($sallesLibres)) {
                        $creneauxLibres[] = [
                            'horaire' => $horaire,
                            'annee' => $annee,
                            'formateurs_disponibles' => $formateursLibres,
                            'salles_disponibles' => $sallesLibres
                        ];
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $creneauxLibres,
            'date' => $date,
            'departement' => $departement->nom_departement,
            'total_creneaux_libres' => count($creneauxLibres)
        ]);
    }

    // =============================================
    // MÉTHODES PRIVÉES POUR L'EXPORT
    // =============================================

    private function preparerDonneesExport($validated)
    {
        $anneeIds = Annee::where('departement_id', $validated['departement_id'])->pluck('id');
        
        $query = EmploiDuTemps::with(['annee.departement', 'formateur.user', 'salle', 'competence'])
            ->whereIn('annee_id', $anneeIds)
            ->whereBetween('date_debut', [$validated['date_debut'], $validated['date_fin']]);

        // Filtrer selon le type
        switch ($validated['type']) {
            case 'formateur':
                if (isset($validated['entity_id'])) {
                    $query->where('formateur_id', $validated['entity_id']);
                }
                break;
            case 'annee':
                if (isset($validated['entity_id'])) {
                    $query->where('annee_id', $validated['entity_id']);
                }
                break;
        }

        return $query->orderBy('date_debut')->orderBy('heure_debut')->get();
    }

    private function exporterPDF($donnees, $validated)
    {
        // Implémentation de l'export PDF
        // Cette méthode nécessiterait une librairie comme DomPDF ou TCPDF
        return response()->json([
            'success' => false,
            'message' => 'Export PDF non implémenté. Veuillez utiliser une librairie PDF.'
        ], 501);
    }

    private function exporterExcel($donnees, $validated)
    {
        // Implémentation de l'export Excel
        // Cette méthode nécessiterait une librairie comme PhpSpreadsheet
        return response()->json([
            'success' => false,
            'message' => 'Export Excel non implémenté. Veuillez utiliser une librairie Excel.'
        ], 501);
    }
}
?>