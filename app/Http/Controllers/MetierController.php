<?php

namespace App\Http\Controllers;

use App\Models\Metier;
use App\Models\Competence;
use App\Models\Formateur;
use App\Models\EmploiDuTemps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MetierController extends Controller
{
    // ================================
    // MÉTHODES CRUD EXISTANTES
    // ================================

    // Liste de tous les métiers
    public function index()
    {
        return Metier::with(['niveau', 'departement'])->get();
    }

    // Création d'un nouveau métier
    public function store(Request $request)
    {
        $validated = $request->validate([
            'intitule' => 'required|string|max:255',
            'duree' => 'required|string|max:255',
            'niveau_id' => 'required|exists:niveaux,id',
            'departement_id' => 'required|exists:departements,id',
            'description' => 'nullable|string|max:1000'
        ]);

        $metier = Metier::create($validated);
        return response()->json($metier->load(['niveau', 'departement']), 201);
    }

    // Afficher un métier spécifique
    public function show($id)
    {
        $metier = Metier::with(['niveau', 'departement', 'competences'])->find($id);
        if (!$metier) {
            return response()->json(['message' => 'Métier non trouvé'], 404);
        }

        return response()->json($metier);
    }

    // Mettre à jour un métier
    public function update(Request $request, $id)
    {
        $metier = Metier::find($id);
        if (!$metier) {
            return response()->json(['message' => 'Métier non trouvé'], 404);
        }

        $validated = $request->validate([
            'intitule' => 'required|string|max:255',
            'duree' => 'required|string|max:255',
            'niveau_id' => 'required|exists:niveaux,id',
            'departement_id' => 'required|exists:departements,id',
            'description' => 'nullable|string|max:1000'
        ]);

        $metier->update($validated);
        return response()->json($metier->load(['niveau', 'departement']));
    }

    // Supprimer un métier
    public function destroy($id)
    {
        $metier = Metier::find($id);
        if (!$metier) {
            return response()->json(['message' => 'Métier non trouvé'], 404);
        }

        $metier->delete();
        return response()->json(['message' => 'Métier supprimé avec succès']);
    }

    // ================================
    // 🆕 NOUVELLES MÉTHODES POUR LA PLANIFICATION
    // ================================

    /**
     * 🆕 RÉCUPÉRER TOUS LES MÉTIERS AVEC LEURS COMPÉTENCES DISPONIBLES
     */
    public function getMetiersAvecCompetences()
    {
        $user = Auth::user();
        $formateur = Formateur::where('user_id', $user->id)->first();

        if (!$formateur) {
            return response()->json([
                'success' => false,
                'message' => 'Formateur non trouvé.'
            ], 404);
        }

        // Récupérer les métiers du département géré par ce formateur
        $metiers = Metier::whereHas('departement', function($query) use ($formateur) {
            $query->where('formateur_id', $formateur->id);
        })
        ->whereHas('competences', function($query) {
            // Seulement les métiers qui ont des compétences avec quota restant
            $query->where('quota_horaire', '>', 0);
        })
        ->with([
            'departement',
            'niveau',
            'competences' => function($query) {
                $query->where('quota_horaire', '>', 0);
            }
        ])
        ->get();

        \Log::info("🎯 {$metiers->count()} métiers avec compétences trouvés pour le formateur {$formateur->id}");

        $metiersFormates = $metiers->map(function($metier) {
            // Calculer les compétences avec quota restant
            $competencesAvecQuota = 0;
            $totalQuotaRestant = 0;

            foreach ($metier->competences as $competence) {
                $quotaTotal = floatval($competence->quota_horaire ?? 0);
                $heuresPlanifiees = $this->calculerHeuresPlanifiees($competence->id);
                $heuresRestantes = max(0, $quotaTotal - $heuresPlanifiees);
                
                if ($heuresRestantes > 0) {
                    $competencesAvecQuota++;
                    $totalQuotaRestant += $heuresRestantes;
                }
            }

            return [
                'id' => $metier->id,
                'intitule' => $metier->intitule,
                'description' => $metier->description,
                'duree' => $metier->duree,
                'departement' => $metier->departement ? [
                    'id' => $metier->departement->id,
                    'nom_departement' => $metier->departement->nom_departement,
                ] : null,
                'niveau' => $metier->niveau ? [
                    'id' => $metier->niveau->id,
                    'intitule' => $metier->niveau->intitule,
                ] : null,
                'competences_count' => $competencesAvecQuota,
                'total_competences' => $metier->competences->count(),
                'total_quota_restant' => round($totalQuotaRestant, 1),
            ];
        })
        ->filter(function($metier) {
            // Garder seulement les métiers avec au moins une compétence disponible
            return $metier['competences_count'] > 0;
        })
        ->values();

        return response()->json([
            'success' => true,
            'data' => $metiersFormates,
            'total_metiers' => $metiersFormates->count(),
            'departement_formateur' => $formateur->id
        ]);
    }

    /**
     * 🆕 RÉCUPÉRER LES COMPÉTENCES D'UN MÉTIER SPÉCIFIQUE
     */
    public function getCompetencesMetier($metierId)
    {
        $user = Auth::user();
        $formateur = Formateur::where('user_id', $user->id)->first();

        if (!$formateur) {
            return response()->json([
                'success' => false,
                'message' => 'Formateur non trouvé.'
            ], 404);
        }

        // Vérifier que ce métier appartient au département géré
        $metier = Metier::with(['departement', 'niveau'])
            ->whereHas('departement', function($query) use ($formateur) {
                $query->where('formateur_id', $formateur->id);
            })
            ->find($metierId);

        if (!$metier) {
            return response()->json([
                'success' => false,
                'message' => 'Métier non trouvé ou non autorisé.'
            ], 404);
        }

        $competences = Competence::where('metier_id', $metierId)
            ->with(['formateur.user', 'salle.batiment'])
            ->get();

        $competencesFormatees = $competences->map(function($competence) use ($metier) {
            $quotaTotal = floatval($competence->quota_horaire ?? 0);
            $heuresPlanifiees = $this->calculerHeuresPlanifiees($competence->id);
            $heuresRestantes = max(0, $quotaTotal - $heuresPlanifiees);

            return [
                'id' => $competence->id,
                'nom' => $competence->nom,
                'code' => $competence->code,
                'description' => $competence->description,
                'quota_total' => $quotaTotal,
                'heures_planifiees' => $heuresPlanifiees,
                'heures_restantes' => $heuresRestantes,
                'pourcentage_complete' => $quotaTotal > 0 ? round(($heuresPlanifiees / $quotaTotal) * 100, 1) : 0,
                'statut' => $heuresRestantes > 0 ? 'disponible' : 'complete',
                'formateur' => $competence->formateur ? [
                    'id' => $competence->formateur->id,
                    'nom' => $competence->formateur->user->nom,
                    'prenom' => $competence->formateur->user->prenom,
                    'email' => $competence->formateur->user->email,
                ] : null,
                'salle' => $competence->salle ? [
                    'id' => $competence->salle->id,
                    'intitule' => $competence->salle->intitule,
                    'capacite' => $competence->salle->capacite,
                    'batiment' => $competence->salle->batiment ? [
                        'id' => $competence->salle->batiment->id,
                        'intitule' => $competence->salle->batiment->intitule
                    ] : null
                ] : null
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $competencesFormatees,
            'metier_info' => [
                'id' => $metier->id,
                'intitule' => $metier->intitule,
                'description' => $metier->description,
                'duree' => $metier->duree,
                'departement' => $metier->departement->nom_departement,
                'niveau' => $metier->niveau->intitule ?? 'Non défini',
            ],
            'summary' => [
                'total_competences' => $competencesFormatees->count(),
                'competences_disponibles' => $competencesFormatees->where('statut', 'disponible')->count(),
                'competences_completes' => $competencesFormatees->where('statut', 'complete')->count(),
                'quota_total_restant' => $competencesFormatees->sum('heures_restantes'),
            ]
        ]);
    }

    /**
     * 🆕 RÉCUPÉRER SEULEMENT LES COMPÉTENCES AVEC QUOTA RESTANT D'UN MÉTIER
     */
    public function getCompetencesAvecQuotaMetier($metierId)
    {
        $user = Auth::user();
        $formateur = Formateur::where('user_id', $user->id)->first();

        if (!$formateur) {
            return response()->json([
                'success' => false,
                'message' => 'Formateur non trouvé.'
            ], 404);
        }

        // Vérifier que ce métier appartient au département géré
        $metier = Metier::with(['departement', 'niveau'])
            ->whereHas('departement', function($query) use ($formateur) {
                $query->where('formateur_id', $formateur->id);
            })
            ->find($metierId);

        if (!$metier) {
            return response()->json([
                'success' => false,
                'message' => 'Métier non trouvé ou non autorisé.'
            ], 404);
        }

        $competences = Competence::where('metier_id', $metierId)
            ->where('quota_horaire', '>', 0)
            ->with(['formateur.user', 'salle.batiment'])
            ->get();

        $competencesAvecQuota = [];

        foreach ($competences as $competence) {
            $quotaTotal = floatval($competence->quota_horaire ?? 0);
            $heuresPlanifiees = $this->calculerHeuresPlanifiees($competence->id);
            $heuresRestantes = max(0, $quotaTotal - $heuresPlanifiees);

            // Inclure seulement les compétences avec quota restant
            if ($heuresRestantes > 0) {
                $competencesAvecQuota[] = [
                    'id' => $competence->id,
                    'nom' => $competence->nom,
                    'code' => $competence->code,
                    'description' => $competence->description,
                    'quota_total' => $quotaTotal,
                    'heures_planifiees' => $heuresPlanifiees,
                    'heures_restantes' => $heuresRestantes,
                    'pourcentage_utilise' => round(($heuresPlanifiees / $quotaTotal) * 100, 1),
                    'formateur' => $competence->formateur ? [
                        'id' => $competence->formateur->id,
                        'nom' => $competence->formateur->user->nom,
                        'prenom' => $competence->formateur->user->prenom
                    ] : null,
                    'metier' => [
                        'id' => $metier->id,
                        'intitule' => $metier->intitule,
                        'departement' => $metier->departement ? [
                            'id' => $metier->departement->id,
                            'nom_departement' => $metier->departement->nom_departement,
                        ] : null,
                        'niveau' => $metier->niveau ? [
                            'id' => $metier->niveau->id,
                            'intitule' => $metier->niveau->intitule,
                        ] : null,
                    ],
                    'salle' => $competence->salle ? [
                        'id' => $competence->salle->id,
                        'intitule' => $competence->salle->intitule,
                        'batiment' => $competence->salle->batiment ? [
                            'id' => $competence->salle->batiment->id,
                            'intitule' => $competence->salle->batiment->intitule
                        ] : null
                    ] : null
                ];
            }
        }

        // Trier par heures restantes (décroissant)
        usort($competencesAvecQuota, function($a, $b) {
            return $b['heures_restantes'] <=> $a['heures_restantes'];
        });

        return response()->json([
            'success' => true,
            'data' => $competencesAvecQuota,
            'metier_info' => [
                'id' => $metier->id,
                'intitule' => $metier->intitule,
                'departement' => $metier->departement->nom_departement,
                'niveau' => $metier->niveau->intitule ?? 'Non défini',
            ],
            'total_competences_disponibles' => count($competencesAvecQuota),
            'total_quota_restant' => array_sum(array_column($competencesAvecQuota, 'heures_restantes'))
        ]);
    }

    /**
     * 🆕 STATISTIQUES D'UN MÉTIER
     */
    public function getStatistiquesMetier($metierId)
    {
        $user = Auth::user();
        $formateur = Formateur::where('user_id', $user->id)->first();

        if (!$formateur) {
            return response()->json([
                'success' => false,
                'message' => 'Formateur non trouvé.'
            ], 404);
        }

        $metier = Metier::with(['departement', 'niveau', 'competences'])
            ->whereHas('departement', function($query) use ($formateur) {
                $query->where('formateur_id', $formateur->id);
            })
            ->find($metierId);

        if (!$metier) {
            return response()->json([
                'success' => false,
                'message' => 'Métier non trouvé ou non autorisé.'
            ], 404);
        }

        $statistiques = [
            'metier_info' => [
                'id' => $metier->id,
                'intitule' => $metier->intitule,
                'description' => $metier->description,
                'departement' => $metier->departement->nom_departement,
                'niveau' => $metier->niveau->intitule ?? 'Non défini',
                'duree_formation' => $metier->duree,
            ],
            'competences' => [
                'total' => $metier->competences->count(),
                'avec_quota' => $metier->competences->where('quota_horaire', '>', 0)->count(),
                'sans_quota' => $metier->competences->where('quota_horaire', '<=', 0)->count(),
            ],
            'quotas' => [
                'total_quota' => $metier->competences->sum('quota_horaire'),
                'heures_planifiees' => 0,
                'heures_restantes' => 0,
                'pourcentage_realise' => 0,
            ],
            'formateurs' => [],
            'emplois_du_temps' => 0,
        ];

        // Calculer les heures planifiées et formateurs
        $formateursUniques = [];
        $emploisDuTempsCount = 0;

        foreach ($metier->competences as $competence) {
            $heuresPlanifiees = $this->calculerHeuresPlanifiees($competence->id);
            $statistiques['quotas']['heures_planifiees'] += $heuresPlanifiees;

            if ($competence->formateur && !in_array($competence->formateur->id, $formateursUniques)) {
                $formateursUniques[] = $competence->formateur->id;
                $statistiques['formateurs'][] = [
                    'id' => $competence->formateur->id,
                    'nom' => $competence->formateur->user->nom,
                    'prenom' => $competence->formateur->user->prenom,
                ];
            }

            // Compter les emplois du temps
            $emploisDuTempsCount += EmploiDuTemps::whereHas('compemplois', function($query) use ($competence) {
                $query->where('competence_id', $competence->id);
            })->count();
        }

        $statistiques['quotas']['heures_restantes'] = max(0, $statistiques['quotas']['total_quota'] - $statistiques['quotas']['heures_planifiees']);
        $statistiques['quotas']['pourcentage_realise'] = $statistiques['quotas']['total_quota'] > 0 
            ? round(($statistiques['quotas']['heures_planifiees'] / $statistiques['quotas']['total_quota']) * 100, 1) 
            : 0;

        $statistiques['emplois_du_temps'] = $emploisDuTempsCount;
        $statistiques['formateurs_count'] = count($formateursUniques);

        return response()->json([
            'success' => true,
            'data' => $statistiques
        ]);
    }

    // ================================
    // 🔧 MÉTHODES HELPER PRIVÉES
    // ================================

    /**
     * 🔧 CALCULER LES HEURES PLANIFIÉES POUR UNE COMPÉTENCE
     */
    private function calculerHeuresPlanifiees($competenceId)
    {
        $emplois = EmploiDuTemps::whereHas('compemplois', function($query) use ($competenceId) {
            $query->where('competence_id', $competenceId);
        })->get();

        $totalHeures = 0;

        foreach ($emplois as $emploi) {
            try {
                // Extraire les heures des strings datetime
                $heureDebutStr = $emploi->heure_debut;
                $heureFinStr = $emploi->heure_fin;
                
                // Extraire juste la partie heure "HH:MM"
                $heureDebut = substr($heureDebutStr, 11, 5);
                $heureFin = substr($heureFinStr, 11, 5);
                
                // Convertir en minutes depuis minuit
                $minutesDebut = $this->heureEnMinutes($heureDebut);
                $minutesFin = $this->heureEnMinutes($heureFin);
                
                // Calculer la durée
                if ($minutesFin > $minutesDebut) {
                    $dureeMinutes = $minutesFin - $minutesDebut;
                    $dureeHeures = $dureeMinutes / 60;
                    $totalHeures += $dureeHeures;
                }
                
            } catch (\Exception $e) {
                \Log::error("Erreur calcul emploi {$emploi->id}: " . $e->getMessage());
            }
        }

        return round($totalHeures, 2);
    }

    /**
     * 🔧 CONVERTIR UNE HEURE "HH:MM" EN MINUTES DEPUIS MINUIT
     */
    private function heureEnMinutes($heure)
    {
        $parts = explode(':', $heure);
        $heures = intval($parts[0]);
        $minutes = intval($parts[1]);
        return ($heures * 60) + $minutes;
    }
}