<?php

namespace App\Http\Controllers;

use App\Models\EmploiDuTemps;
use App\Models\Formateur;
use App\Models\Departement;
use App\Models\Competence;
use App\Models\Annee;
use App\Services\GenerationEmploiService;  // 👈 AJOUTER CETTE LIGNE
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class EmploiDuTempsController extends Controller
{
     private $creneauxHoraires = [
        ['debut' => '08:00', 'fin' => '09:00'],
        ['debut' => '09:00', 'fin' => '10:00'],
        ['debut' => '10:00', 'fin' => '11:00'],
        ['debut' => '11:00', 'fin' => '12:00'],
        ['debut' => '12:00', 'fin' => '13:00'],
        // PAUSE 13h-14h
        ['debut' => '14:00', 'fin' => '15:00'],
        ['debut' => '15:00', 'fin' => '16:00'],
        ['debut' => '16:00', 'fin' => '17:00'],
    
    ];
     private $joursSemaine = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
    // 👈 AJOUTER LE SERVICE EN INJECTION DE DÉPENDANCE
    public function __construct(
        private GenerationEmploiService $generationService
    ) {}

    /**
     * Créer un nouveau créneau d'emploi du temps
     * 
     * Le processus :
     * 1. Créer l'emploi du temps (date, heure, année)
     * 2. Associer les compétences via la table compemplois
     * 3. Chaque compétence est liée à un formateur et peut avoir une salle
     */
 public function store(Request $request)
{
    $validated = $request->validate([
        'annee_id' => 'required|exists:annees,id',
        'heure_debut' => 'required|date_format:H:i:s',
        'heure_fin' => 'required|date_format:H:i:s|after:heure_debut',
        'date_debut' => 'required|date',
        'date_fin' => 'required|date|after_or_equal:date_debut',
        'competences' => 'nullable|array',
        'competences.*' => 'exists:competences,id'
    ]);

    // 👈 AJOUT : Debug pour voir ce qui est reçu
    \Log::info('📝 Données reçues dans store():', [
        'annee_id' => $validated['annee_id'],
        'date' => $validated['date_debut'],
        'heure' => $validated['heure_debut'] . ' - ' . $validated['heure_fin'],
        'competences' => $validated['competences'] ?? 'AUCUNE'
    ]);

    // Vérifier que l'utilisateur est chef du département de cette année
    if (!$this->verifierDroitsCreation($validated['annee_id'])) {
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

    DB::beginTransaction();
    try {
        // 1. Créer l'emploi du temps
        $emploi = EmploiDuTemps::create([
            'annee_id' => $validated['annee_id'],
            'heure_debut' => $validated['heure_debut'],
            'heure_fin' => $validated['heure_fin'],
            'date_debut' => $validated['date_debut'],
            'date_fin' => $validated['date_fin'],
        ]);

        \Log::info("✅ Emploi du temps créé avec ID: {$emploi->id}");

        // 2. Associer les compétences SEULEMENT si elles sont fournies
        if (!empty($validated['competences'])) {
            \Log::info('🎯 Insertion de ' . count($validated['competences']) . ' compétences');
            
            foreach ($validated['competences'] as $competenceId) {
                DB::table('compemplois')->insert([
                    'emploi_du_temps_id' => $emploi->id,
                    'competence_id' => $competenceId,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                \Log::info("📝 CompEmploi créé: Emploi {$emploi->id} ↔ Compétence {$competenceId}");
            }
            
            // Vérifier que les compemplois ont été créés
            $nombreCompEmplois = DB::table('compemplois')
                ->where('emploi_du_temps_id', $emploi->id)
                ->count();
            \Log::info("✅ {$nombreCompEmplois} compemplois créés pour l'emploi {$emploi->id}");
            
        } else {
            \Log::warning("⚠️ AUCUNE compétence fournie pour l'emploi du temps {$emploi->id}");
        }

        DB::commit();

        // Recharger avec les relations
        $emploi = EmploiDuTemps::with([
            'annee',
            'compemplois.competence.formateur.user',
            'compemplois.competence.metier.departement', // 👈 AJOUT: Charger aussi le département
            'compemplois.competence.salle'
        ])->find($emploi->id);

        \Log::info("🔄 Emploi rechargé avec " . $emploi->compemplois->count() . " compemplois");

        return response()->json([
            'success' => true,
            'message' => 'Créneau créé avec succès',
            'data' => $emploi
        ], 201);

    } catch (\Exception $e) {
        DB::rollback();
        \Log::error("❌ Erreur création emploi du temps: " . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la création: ' . $e->getMessage()
        ], 500);
    }
}

    /**
     * Récupérer tous les emplois du temps avec les relations
     */
 public function index()
{
    $user = Auth::user();
    $formateur = Formateur::where('user_id', $user->id)->first();

    if (!$formateur) {
        return response()->json(['success' => false, 'message' => 'Formateur non trouvé.'], 404);
    }

    $departementsGeres = Departement::where('formateur_id', $formateur->id)->get();
    if ($departementsGeres->isEmpty()) {
        return response()->json(['success' => false, 'message' => 'Vous ne gérez aucun département.'], 403);
    }

    $departementIds = $departementsGeres->pluck('id')->toArray();

    // 🔥 CORRECTION : Ajouter metier.niveau dans les relations
    $emplois = EmploiDuTemps::with([
        'annee',
        'compemplois.competence.formateur.user',
        'compemplois.competence.metier.departement',
        'compemplois.competence.metier.niveau',  // 🔥 AJOUTÉ
        'compemplois.competence.salle.batiment'
    ])
    ->whereHas('compemplois.competence.metier.departement', function($query) use ($departementIds) {
        $query->whereIn('id', $departementIds);
    })
    ->orderBy('date_debut')
    ->orderBy('heure_debut')
    ->get();

    $emploisFormates = $emplois->map(function($emploi) {
        $departement = null;
        if ($emploi->compemplois->isNotEmpty()) {
            $premierCompemploi = $emploi->compemplois->first();
            if ($premierCompemploi && $premierCompemploi->competence && $premierCompemploi->competence->metier) {
                $departement = $premierCompemploi->competence->metier->departement;
            }
        }

        return [
            'id' => $emploi->id,
            'annee' => [
                'id' => $emploi->annee->id,
                'intitule' => $emploi->annee->intitule,
                'departement' => $departement ? [
                    'id' => $departement->id,
                    'nom_departement' => $departement->nom_departement,
                ] : null
            ],
            'date_debut' => $emploi->date_debut,
            'date_fin' => $emploi->date_fin,
            'heure_debut' => $emploi->heure_debut,
            'heure_fin' => $emploi->heure_fin,
            // 🔥 CORRECTION PRINCIPALE : Ajouter le métier dans les compétences
            'competences' => $emploi->compemplois->map(function($compEmploi) {
                $competence = $compEmploi->competence;
                return [
                    'id' => $competence->id,
                    'nom' => $competence->nom,
                    'code' => $competence->code,
                    // 🔥 MÉTIER COMPLET AJOUTÉ
                    'metier' => $competence->metier ? [
                        'id' => $competence->metier->id,
                        'intitule' => $competence->metier->intitule,
                        'duree' => $competence->metier->duree,
                        'departement' => $competence->metier->departement ? [
                            'id' => $competence->metier->departement->id,
                            'nom_departement' => $competence->metier->departement->nom_departement,
                        ] : null,
                        'niveau' => $competence->metier->niveau ? [
                            'id' => $competence->metier->niveau->id,
                            'intitule' => $competence->metier->niveau->intitule,
                        ] : null,
                    ] : null,
                    'formateur' => $competence->formateur ? [
                        'id' => $competence->formateur->id,
                        'nom' => $competence->formateur->user->nom,
                        'prenom' => $competence->formateur->user->prenom
                    ] : null,
                    'salle' => $competence->salle ? [
                        'id' => $competence->salle->id,
                        'intitule' => $competence->salle->intitule,
                        'batiment' => $competence->salle->batiment ? [
                            'id' => $competence->salle->batiment->id,
                            'intitule' => $competence->salle->batiment->intitule
                        ] : null
                    ] : null
                ];
            })
        ];
    });

    return response()->json([
        'success' => true,
        'data' => $emploisFormates,
        'departements_geres' => $departementsGeres
    ]);
}

    // 👈 NOUVELLES MÉTHODES QUI UTILISENT LE SERVICE

    /**
     * Génération automatique d'emploi du temps
     * ✅ BON : Utilise le service pour la logique complexe
     */
    public function genererAutomatique(Request $request)
    {
        $validated = $request->validate([
            'departement_id' => 'required|exists:departements,id',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after:date_debut'
        ]);

        // Vérifier les droits
        if (!$this->verifierChefDepartement($validated['departement_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez générer que pour vos départements.'
            ], 403);
        }

        try {
            // 👈 UTILISER LE SERVICE POUR LA LOGIQUE COMPLEXE
            $result = $this->generationService->genererEmploiDuTemps(
                $validated['departement_id'],
                $validated['date_debut'],
                $validated['date_fin']
            );

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur : ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Analyser l'emploi du temps
     */
    public function analyserEmploi(Request $request)
    {
        $validated = $request->validate([
            'departement_id' => 'required|exists:departements,id',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date'
        ]);

        if (!$this->verifierChefDepartement($validated['departement_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé.'
            ], 403);
        }

        // 👈 UTILISER LE SERVICE
        $analyse = $this->generationService->analyserEmploiDuTemps(
            $validated['departement_id'],
            $validated['date_debut'],
            $validated['date_fin']
        );

        return response()->json([
            'success' => true,
            'data' => $analyse
        ]);
    }

    /**
     * Générer un rapport d'occupation
     */
    public function genererRapport(Request $request)
    {
        $validated = $request->validate([
            'departement_id' => 'required|exists:departements,id',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date'
        ]);

        if (!$this->verifierChefDepartement($validated['departement_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé.'
            ], 403);
        }

        // 👈 UTILISER LE SERVICE
        $rapport = $this->generationService->genererRapportOccupation(
            $validated['departement_id'],
            $validated['date_debut'],
            $validated['date_fin']
        );

        return response()->json([
            'success' => true,
            'data' => $rapport
        ]);
    }

    /**
     * Proposer une réorganisation
     */
    public function proposerReorganisation(Request $request)
    {
        $validated = $request->validate([
            'departement_id' => 'required|exists:departements,id',
            'date_debut' => 'required|date',
            'date_fin' => 'required|date'
        ]);

        if (!$this->verifierChefDepartement($validated['departement_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Accès refusé.'
            ], 403);
        }

        // 👈 UTILISER LE SERVICE
        $propositions = $this->generationService->proposerReorganisation(
            $validated['departement_id'],
            $validated['date_debut'],
            $validated['date_fin']
        );

        return response()->json([
            'success' => true,
            'data' => $propositions
        ]);
    }

    /**
     * Récupérer l'emploi du temps d'un formateur
     */
    public function getFormateurSchedule($formateurId, Request $request)
    {
        $validated = $request->validate([
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after_or_equal:date_debut',
        ]);

        $formateur = Formateur::with('user')->find($formateurId);
        if (!$formateur) {
            return response()->json(['success' => false, 'message' => 'Formateur non trouvé'], 404);
        }

        // Récupérer les emplois du temps où ce formateur enseigne
        $emploiDuTemps = EmploiDuTemps::with([
            'annee.departement', 
            'competences.metier'
        ])
        ->whereHas('competences', function($query) use ($formateurId) {
            $query->where('formateur_id', $formateurId);
        })
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
     * Récupérer l'emploi du temps d'une année
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

        $emploiDuTemps = EmploiDuTemps::with([
            'competences.formateur.user',
            'competences.metier',
            'competences.salle'
        ])
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
 * Récupérer l'emploi du temps de l'élève connecté
 */
public function monEmploi()
{
    $user = Auth::user();
    
    // Récupérer l'élève à partir du user connecté
    $eleve = \App\Models\Eleve::where('user_id', $user->id)->first();
    
    if (!$eleve) {
        return response()->json([
            'success' => false,
            'message' => 'Élève non trouvé.'
        ], 404);
    }

    \Log::info("🎓 Élève trouvé: ID {$eleve->id}, Métier ID: {$eleve->metier_id}");

    // Récupérer les emplois du temps de sa filière/métier
    $emplois = EmploiDuTemps::with([
        'annee',
        'compemplois.competence.formateur.user',
        'compemplois.competence.metier.departement',
        'compemplois.competence.metier.niveau',
        'compemplois.competence.salle.batiment'
    ])
    ->whereHas('compemplois.competence.metier', function($query) use ($eleve) {
        $query->where('id', $eleve->metier_id);
    })
    ->orderBy('date_debut')
    ->orderBy('heure_debut')
    ->get();

    \Log::info("📅 {$emplois->count()} emplois trouvés pour le métier {$eleve->metier_id}");

    // 🔥 FORMATER LES DONNÉES (même logique que index())
    $emploisFormates = $emplois->map(function($emploi) {
        $departement = null;
        if ($emploi->compemplois->isNotEmpty()) {
            $premierCompemploi = $emploi->compemplois->first();
            if ($premierCompemploi && $premierCompemploi->competence && $premierCompemploi->competence->metier) {
                $departement = $premierCompemploi->competence->metier->departement;
            }
        }

        return [
            'id' => $emploi->id,
            'annee' => [
                'id' => $emploi->annee->id,
                'intitule' => $emploi->annee->intitule,
                'departement' => $departement ? [
                    'id' => $departement->id,
                    'nom_departement' => $departement->nom_departement,
                ] : null
            ],
            'date_debut' => $emploi->date_debut,
            'date_fin' => $emploi->date_fin,
            'heure_debut' => $emploi->heure_debut,
            'heure_fin' => $emploi->heure_fin,
            'competences' => $emploi->compemplois->map(function($compEmploi) {
                $competence = $compEmploi->competence;
                return [
                    'id' => $competence->id,
                    'nom' => $competence->nom,
                    'code' => $competence->code,
                    'metier' => $competence->metier ? [
                        'id' => $competence->metier->id,
                        'intitule' => $competence->metier->intitule,
                        'duree' => $competence->metier->duree,
                        'departement' => $competence->metier->departement ? [
                            'id' => $competence->metier->departement->id,
                            'nom_departement' => $competence->metier->departement->nom_departement,
                        ] : null,
                        'niveau' => $competence->metier->niveau ? [
                            'id' => $competence->metier->niveau->id,
                            'intitule' => $competence->metier->niveau->intitule,
                        ] : null,
                    ] : null,
                    // 🚫 PAS DE DÉTAILS FORMATEUR pour les élèves
                    'formateur' => null,
                    'salle' => $competence->salle ? [
                        'id' => $competence->salle->id,
                        'intitule' => $competence->salle->intitule,
                        'batiment' => $competence->salle->batiment ? [
                            'id' => $competence->salle->batiment->id,
                            'intitule' => $competence->salle->batiment->intitule
                        ] : null
                    ] : null
                ];
            })
        ];
    });

    return response()->json([
        'success' => true,
        'data' => $emploisFormates,
        'eleve_info' => [
            'id' => $eleve->id,
            'metier_id' => $eleve->metier_id,
            'user' => [
                'nom' => $user->nom,
                'prenom' => $user->prenom
            ]
        ]
    ]);
}


    // ==============================
    // MÉTHODES PRIVÉES (inchangées)
    // ==============================

    /**
     * Vérifier les droits de création pour une année donnée
     */
   private function verifierDroitsCreation($anneeId)
{
    $user = Auth::user();
    $formateur = Formateur::where('user_id', $user->id)->first();

    if (!$formateur) {
        return false;
    }

    // Selon votre architecture : emploi_du_temps → compemplois → competences → metiers → departements
    // On vérifie si le formateur est chef d'un département qui a des métiers avec des compétences
    
    $estChef = Departement::where('formateur_id', $formateur->id)
                         ->whereHas('metiers', function($q) {
                             $q->whereHas('competences');
                         })
                         ->exists();
    
    return $estChef;
}

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
    $metiers = \App\Models\Metier::whereHas('departement', function($query) use ($formateur) {
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
            'description' => $metier->description ?? null,
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
     * Vérifier si l'utilisateur est chef d'un département
     */
    private function verifierChefDepartement($departementId = null)
    {
        $user = Auth::user();
        $formateur = Formateur::where('user_id', $user->id)->first();

        if (!$formateur) {
            return false;
        }

        // Si département spécifique fourni
        if ($departementId) {
            $departement = Departement::find($departementId);
            return $departement && $departement->formateur_id == $formateur->id;
        }

        // Vérifier si le formateur est chef d'au moins un département
        return Departement::where('formateur_id', $formateur->id)->exists();
    }

    /**
 * Calculer les heures planifiées pour une compétence
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
            $heureDebutStr = $emploi->heure_debut; // "2025-06-17T08:00:00.000000Z"
            $heureFinStr = $emploi->heure_fin;     // "2025-06-17T10:00:00.000000Z"
            
            // Extraire juste la partie heure "HH:MM"
            $heureDebut = substr($heureDebutStr, 11, 5); // "08:00"
            $heureFin = substr($heureFinStr, 11, 5);     // "10:00"
            
            // Convertir en minutes depuis minuit
            $minutesDebut = $this->heureEnMinutes($heureDebut);
            $minutesFin = $this->heureEnMinutes($heureFin);
            
            // Calculer la durée
            if ($minutesFin > $minutesDebut) {
                $dureeMinutes = $minutesFin - $minutesDebut;
                $dureeHeures = $dureeMinutes / 60;
                
                $totalHeures += $dureeHeures;
                
                \Log::info("Emploi {$emploi->id}: {$heureDebut} → {$heureFin} = {$dureeHeures}h");
            }
            
        } catch (\Exception $e) {
            \Log::error("Erreur calcul emploi {$emploi->id}: " . $e->getMessage());
        }
    }

    \Log::info("Compétence {$competenceId}: Total = {$totalHeures}h");
    return round($totalHeures, 2);
}

 private function creerCreneauIndividuel($creneauData)
    {
        // Créer l'emploi du temps
        $emploi = EmploiDuTemps::create([
            'annee_id' => $creneauData['annee_id'],
            'heure_debut' => $creneauData['heure_debut'],
            'heure_fin' => $creneauData['heure_fin'],
            'date_debut' => $creneauData['date'],
            'date_fin' => $creneauData['date'],
        ]);

        // Associer la compétence
        DB::table('compemplois')->insert([
            'emploi_du_temps_id' => $emploi->id,
            'competence_id' => $creneauData['competence']->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return $emploi;
    }

 private function genererCreneauxPourDuree($indexDebut, $dureeHeures)
    {
        $creneaux = [];
        $indexActuel = $indexDebut;

        for ($h = 0; $h < $dureeHeures; $h++) {
            // Vérifier qu'on ne dépasse pas la journée
            if ($indexActuel >= count($this->creneauxHoraires)) {
                return []; // Impossible de caser ce cours
            }

            // Vérifier qu'on ne traverse pas la pause déjeuner (index 4-5 = 12h-14h)
            if ($indexActuel == 4 && $h < $dureeHeures - 1) {
                // Si on est à 12h et qu'il reste des heures, passer après la pause
                $indexActuel = 5; // 14h
            }

            $creneaux[] = $indexActuel;
            $indexActuel++;
        }

        return $creneaux;
    }

private function genererPlanificationIntelligente($competencesConfig, $anneeId, $dateDebut)
    {
        $planning = [];
        $dateActuelle = Carbon::parse($dateDebut);
        
        // Suivi des créneaux utilisés par jour pour éviter les conflits
        $creneauxOccupes = []; // [date][creneau_index] = true
        
        // Index de décalage pour chaque compétence
        $decalageParCompetence = [];

        foreach ($competencesConfig as $configCompetence) {
            $competence = Competence::with(['formateur.user', 'metier', 'salle.batiment'])
                                   ->find($configCompetence['id']);
            
            if (!$competence) continue;

            $dureeCoursHeures = $configCompetence['duree_cours'];
            $quotaRestant = floatval($competence->quota_horaire ?? 0);
            
            // Calculer heures déjà planifiées
            $heuresPlanifiees = $this->calculerHeuresPlanifiees($competence->id);
            $heuresRestantes = max(0, $quotaRestant - $heuresPlanifiees);
            
            // Calculer nombre de séances nécessaires
            $nombreSeances = ceil($heuresRestantes / $dureeCoursHeures);

            \Log::info("📚 {$competence->nom}: {$heuresRestantes}h ÷ {$dureeCoursHeures}h = {$nombreSeances} séances");

            // Planifier chaque séance avec décalage intelligent
            for ($i = 0; $i < $nombreSeances; $i++) {
                $creneauTrouve = false;
                $tentatives = 0;
                $dateRecherche = clone $dateActuelle;

                while (!$creneauTrouve && $tentatives < 30) { // Max 30 jours de recherche
                    // Obtenir le prochain créneau pour cette compétence (avec décalage)
                    $indexCreneau = ($decalageParCompetence[$competence->id] ?? 0) % count($this->creneauxHoraires);
                    
                    $dateStr = $dateRecherche->format('Y-m-d');
                    
                    // Vérifier si ce créneau est libre ce jour-là
                    if (!isset($creneauxOccupes[$dateStr][$indexCreneau])) {
                        // Vérifier que c'est un jour ouvrable (pas dimanche)
                        if ($dateRecherche->dayOfWeek != 0) {
                            // Générer les créneaux selon la durée
                            $creneauxCours = $this->genererCreneauxPourDuree($indexCreneau, $dureeCoursHeures);
                            
                            if (!empty($creneauxCours)) {
                                // Marquer tous les créneaux comme occupés
                                foreach ($creneauxCours as $creneauIndex) {
                                    $creneauxOccupes[$dateStr][$creneauIndex] = true;
                                }

                                $planning[] = [
                                    'competence' => $competence,
                                    'annee_id' => $anneeId,
                                    'date' => $dateRecherche->format('Y-m-d'),
                                    'heure_debut' => $this->creneauxHoraires[$creneauxCours[0]]['debut'] . ':00',
                                    'heure_fin' => $this->creneauxHoraires[end($creneauxCours)]['fin'] . ':00',
                                    'duree_heures' => $dureeCoursHeures,
                                    'numero_seance' => $i + 1,
                                    'jour_nom' => $this->joursSemaine[$dateRecherche->dayOfWeek - 1]
                                ];

                                // Incrémenter le décalage pour cette compétence
                                $decalageParCompetence[$competence->id] = ($decalageParCompetence[$competence->id] ?? 0) + 2;
                                
                                $creneauTrouve = true;
                                
                               // Log::info("✅ {$competence->nom} séance {$i+1}: {$dateRecherche->format('D d/m')} {$this->creneauxHoraires[$creneauxCours[0]]['debut']}-{$this->creneauxHoraires[end($creneauxCours)]['fin']}");
                            }
                        }
                    }

                    if (!$creneauTrouve) {
                        // Passer au jour suivant
                        $dateRecherche->addDay();
                        $tentatives++;
                    }
                }
            }
        }

        // Trier par date puis par heure
        usort($planning, function($a, $b) {
            $dateComp = strcmp($a['date'], $b['date']);
            if ($dateComp !== 0) return $dateComp;
            return strcmp($a['heure_debut'], $b['heure_debut']);
        });

        return $planning;
    }

 public function planifierCompetences(Request $request)
    {
        $validated = $request->validate([
            'annee_id' => 'required|exists:annees,id',
            'date_debut' => 'required|date',
            'competences' => 'required|array|min:1',
            'competences.*.id' => 'required|exists:competences,id',
            'competences.*.duree_cours' => 'required|integer|min:1|max:4', // 1h à 4h
        ]);

        // Vérifier les droits
        if (!$this->verifierDroitsCreation($validated['annee_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Vous ne pouvez créer des créneaux que pour vos départements.'
            ], 403);
        }

        DB::beginTransaction();
        try {
            $planification = $this->genererPlanificationIntelligente(
                $validated['competences'],
                $validated['annee_id'],
                $validated['date_debut']
            );

            // Créer tous les créneaux
            $creneauxCrees = [];
            foreach ($planification as $creneau) {
                $emploi = $this->creerCreneauIndividuel($creneau);
                $creneauxCrees[] = $emploi;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($creneauxCrees) . ' créneaux créés avec succès',
                'data' => [
                    'creneaux_crees' => count($creneauxCrees),
                    'planification' => $planification,
                    'emplois' => $creneauxCrees
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            \Log::error("❌ Erreur planification: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la planification: ' . $e->getMessage()
            ], 500);
        }
    }

/**
 * Convertir une heure "HH:MM" en minutes depuis minuit
 */
private function heureEnMinutes($heure)
{
    $parts = explode(':', $heure);
    $heures = intval($parts[0]);
    $minutes = intval($parts[1]);
    return ($heures * 60) + $minutes;
}

public function planifierIntelligent(Request $request)
{
    $validated = $request->validate([
        'annee_id' => 'required|exists:annees,id',
        'date_debut' => 'required|date',
        'competences' => 'required|array|min:1',
        'competences.*.id' => 'required|exists:competences,id',
        'competences.*.duree_cours' => 'required|integer|min:1|max:4',
        'competences.*.max_seances' => 'integer|min:1|max:10',
        'max_seances_par_competence' => 'required|integer|min:1|max:10',
        'mode' => 'string'
    ]);

    // Vérifier les droits
    if (!$this->verifierDroitsCreation($validated['annee_id'])) {
        return response()->json([
            'success' => false,
            'message' => 'Vous ne pouvez créer des créneaux que pour vos départements.'
        ], 403);
    }

    DB::beginTransaction();
    try {
        // 🎯 NOUVELLE LOGIQUE : Planification avec limitation
        $resultat = $this->genererPlanificationLimitee(
            $validated['competences'],
            $validated['annee_id'],
            $validated['date_debut'],
            $validated['max_seances_par_competence']
        );

        if ($resultat['success']) {
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Planification intelligente créée avec succès !',
                'data' => [
                    'creneaux_crees' => $resultat['creneaux_crees'],
                    'planification' => $resultat['planification'],
                    'resume' => $resultat['resume'],
                    'quotas_mis_a_jour' => $resultat['quotas_mis_a_jour']
                ]
            ], 201);
        } else {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => $resultat['message']
            ], 422);
        }

    } catch (\Exception $e) {
        DB::rollback();
        \Log::error("❌ Erreur planification intelligente: " . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la planification: ' . $e->getMessage()
        ], 500);
    }
}

private function genererPlanificationLimitee($competencesConfig, $anneeId, $dateDebut, $maxSeancesParCompetence)
{
    $planification = [];
    $creneauxCrees = [];
    $quotasMisAJour = [];
    $dateActuelle = Carbon::parse($dateDebut);
    
    // Suivi des créneaux utilisés par jour
    $creneauxOccupes = [];
    
    // Index de décalage pour répartir les compétences
    $decalageParCompetence = [];

    \Log::info("🎯 Début planification limitée - Max {$maxSeancesParCompetence} séances par compétence");

    foreach ($competencesConfig as $configCompetence) {
        $competence = Competence::with(['formateur.user', 'metier', 'salle.batiment'])
                               ->find($configCompetence['id']);
        
        if (!$competence) {
            \Log::warning("❌ Compétence {$configCompetence['id']} non trouvée");
            continue;
        }

        $dureeCoursHeures = $configCompetence['duree_cours'];
        $quotaRestant = floatval($competence->quota_horaire ?? 0);
        
        // Calculer heures déjà planifiées
        $heuresPlanifiees = $this->calculerHeuresPlanifiees($competence->id);
        $heuresRestantes = max(0, $quotaRestant - $heuresPlanifiees);
        
        // 🆕 LIMITATION : Calculer le nombre de séances à créer
        $maxSeancesPossibles = floor($heuresRestantes / $dureeCoursHeures);
        $seancesACréer = min($maxSeancesPossibles, $maxSeancesParCompetence);
        
        \Log::info("📚 {$competence->nom}: {$heuresRestantes}h restantes ÷ {$dureeCoursHeures}h = {$maxSeancesPossibles} max, limité à {$seancesACréer}");

        if ($seancesACréer <= 0) {
            \Log::warning("⚠️ Aucune séance à créer pour {$competence->nom}");
            continue;
        }

        // 🎯 PLANIFIER LES SÉANCES LIMITÉES
        for ($i = 0; $i < $seancesACréer; $i++) {
            $creneauTrouve = false;
            $tentatives = 0;
            $dateRecherche = clone $dateActuelle;

            while (!$creneauTrouve && $tentatives < 30) {
                // Obtenir le prochain créneau avec décalage intelligent
                $indexCreneau = ($decalageParCompetence[$competence->id] ?? 0) % count($this->creneauxHoraires);
                
                $dateStr = $dateRecherche->format('Y-m-d');
                
                // Vérifier disponibilité et jour ouvrable
                if (!isset($creneauxOccupes[$dateStr][$indexCreneau]) && $dateRecherche->dayOfWeek != 0) {
                    
                    // Vérifier les conflits avec la base
                    if (!$this->verifierConflitCreneau($anneeId, $dateStr, $indexCreneau, $dureeCoursHeures, $competence->formateur_id)) {
                        
                        // Générer les créneaux selon la durée
                        $creneauxCours = $this->genererCreneauxPourDuree($indexCreneau, $dureeCoursHeures);
                        
                        if (!empty($creneauxCours)) {
                            // Marquer les créneaux comme occupés
                            foreach ($creneauxCours as $creneauIndex) {
                                $creneauxOccupes[$dateStr][$creneauIndex] = true;
                            }

                            // Créer le créneau
                            $creneau = [
                                'competence' => $competence,
                                'annee_id' => $anneeId,
                                'date' => $dateRecherche->format('Y-m-d'),
                                'heure_debut' => $this->creneauxHoraires[$creneauxCours[0]]['debut'] . ':00',
                                'heure_fin' => $this->creneauxHoraires[end($creneauxCours)]['fin'] . ':00',
                                'duree_heures' => $dureeCoursHeures,
                                'numero_seance' => $i + 1,
                                'total_seances_creees' => $seancesACréer,
                                'jour_nom' => $this->joursSemaine[$dateRecherche->dayOfWeek - 1]
                            ];

                            $planification[] = $creneau;
                            
                            // Créer l'emploi du temps en base
                            $emploiCree = $this->creerCreneauIndividuel($creneau);
                            $creneauxCrees[] = $emploiCree;

                            // Incrémenter le décalage pour éviter la répétition
                            $decalageParCompetence[$competence->id] = ($decalageParCompetence[$competence->id] ?? 0) + 2;
                            
                            $creneauTrouve = true;
                            
                //Log::info("✅ {$competence->nom} séance {$i+1}/{$seancesACréer}: {$dateRecherche->format('D d/m')} {$this->creneauxHoraires[$creneauxCours[0]]['debut']}-{$this->creneauxHoraires[end($creneauxCours)]['fin']}");
                        }
                    }
                }

                if (!$creneauTrouve) {
                    $dateRecherche->addDay();
                    $tentatives++;
                }
            }

            if (!$creneauTrouve) {
               // \Log::warning("⚠️ Impossible de placer la séance {$i+1} pour {$competence->nom}");
            }
        }

        // 🆕 CALCULER ET ENREGISTRER LA MISE À JOUR DU QUOTA
        $heuresUtilisees = $seancesACréer * $dureeCoursHeures;
        $nouveauQuotaRestant = $heuresRestantes - $heuresUtilisees;
        
        $quotasMisAJour[] = [
            'competence_id' => $competence->id,
            'nom' => $competence->nom,
            'heures_utilisees' => $heuresUtilisees,
            'quota_avant' => $heuresRestantes,
            'quota_apres' => $nouveauQuotaRestant,
            'seances_creees' => $seancesACréer
        ];
    }

    // Trier la planification par date puis heure
    usort($planification, function($a, $b) {
        $dateComp = strcmp($a['date'], $b['date']);
        if ($dateComp !== 0) return $dateComp;
        return strcmp($a['heure_debut'], $b['heure_debut']);
    });

    return [
        'success' => true,
        'planification' => $planification,
        'creneaux_crees' => count($creneauxCrees),
        'emplois' => $creneauxCrees,
        'quotas_mis_a_jour' => $quotasMisAJour,
        'resume' => [
            'creneaux_crees' => count($creneauxCrees),
            'competences_traitees' => count($quotasMisAJour),
            'max_seances_par_competence' => $maxSeancesParCompetence,
            'total_heures_planifiees' => array_sum(array_column($quotasMisAJour, 'heures_utilisees'))
        ]
    ];
}



/**
 * Obtenir le statut des quotas pour toutes les compétences
 */
public function getQuotasStatut()
{
    $user = Auth::user();
    $formateur = Formateur::where('user_id', $user->id)->first();

    if (!$formateur) {
        return response()->json([
            'success' => false,
            'message' => 'Formateur non trouvé.'
        ], 404);
    }

    // 👈 CORRECTION : Récupérer seulement les compétences du département géré
    $competences = Competence::whereHas('metier', function($query) use ($formateur) {
        $query->whereHas('departement', function($deptQuery) use ($formateur) {
            $deptQuery->where('formateur_id', $formateur->id);
        });
    })->with(['metier', 'formateur.user'])->get();

    \Log::info("📊 {$competences->count()} compétences trouvées pour le formateur {$formateur->id}");

    $quotasStatut = [];

    foreach ($competences as $competence) {
        $quotaTotal = floatval($competence->quota_horaire ?? 0);
        $heuresPlanifiees = $this->calculerHeuresPlanifiees($competence->id);
        $heuresRestantes = max(0, $quotaTotal - $heuresPlanifiees);
        $pourcentage = $quotaTotal > 0 ? ($heuresPlanifiees / $quotaTotal) * 100 : 0;

        $quotasStatut[] = [
            'competence_id' => $competence->id,
            'nom' => $competence->nom,
            'code' => $competence->code,
            'quota_total' => $quotaTotal,
            'heures_planifiees' => $heuresPlanifiees,
            'heures_restantes' => $heuresRestantes,
            'pourcentage_complete' => round($pourcentage, 1),
            'statut' => $heuresRestantes > 0 ? 'en_cours' : 'termine',
            'formateur' => [
                'nom' => $competence->formateur->user->nom,
                'prenom' => $competence->formateur->user->prenom
            ],
            'metier' => $competence->metier->intitule
        ];
    }

    return response()->json([
        'success' => true,
        'data' => $quotasStatut
    ]);
}

/**
 * Obtenir les compétences avec quota restant (pour l'aide à la création)
 */
public function getCompetencesAvecQuota(Request $request)
{
    $user = Auth::user();
    $formateur = Formateur::where('user_id', $user->id)->first();

    if (!$formateur) {
        return response()->json([
            'success' => false,
            'message' => 'Formateur non trouvé.'
        ], 404);
    }

    // 🆕 NOUVEAU : Support du filtrage par métier via query parameter
    $metierId = $request->query('metier_id');

    $competencesQuery = Competence::whereHas('metier', function($query) use ($formateur) {
        $query->whereHas('departement', function($deptQuery) use ($formateur) {
            $deptQuery->where('formateur_id', $formateur->id);
        });
    });

    // 🆕 FILTRE PAR MÉTIER SI SPÉCIFIÉ
    if ($metierId) {
        $competencesQuery->where('metier_id', $metierId);
        \Log::info("🔍 Filtrage par métier ID: {$metierId}");
    }

    $competences = $competencesQuery->with(['metier.departement', 'metier.niveau', 'formateur.user', 'salle.batiment'])->get();

    \Log::info("🎯 {$competences->count()} compétences trouvées" . ($metierId ? " pour le métier {$metierId}" : "") . " pour le formateur {$formateur->id}");

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
                'quota_total' => $quotaTotal,
                'heures_planifiees' => $heuresPlanifiees,
                'heures_restantes' => $heuresRestantes,
                'formateur' => $competence->formateur ? [
                    'id' => $competence->formateur->id,
                    'nom' => $competence->formateur->user->nom,
                    'prenom' => $competence->formateur->user->prenom
                ] : null,
                'metier' => $competence->metier ? [
                    'id' => $competence->metier->id,
                    'intitule' => $competence->metier->intitule,
                    'departement' => $competence->metier->departement ? [
                        'id' => $competence->metier->departement->id,
                        'nom_departement' => $competence->metier->departement->nom_departement,
                    ] : null,
                    'niveau' => $competence->metier->niveau ? [
                        'id' => $competence->metier->niveau->id,
                        'intitule' => $competence->metier->niveau->intitule,
                    ] : null,
                ] : null,
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

    return response()->json([
        'success' => true,
        'data' => $competencesAvecQuota,
        'filter_applied' => $metierId ? "Métier ID: {$metierId}" : 'Tous les métiers',
        'total_competences' => count($competencesAvecQuota)
    ]);
}


public function getCompetencesAvecQuotaByMetier($metierId)
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
    $metier = \App\Models\Metier::with(['departement', 'niveau'])
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

    // Récupérer toutes les compétences de ce métier
    $competences = Competence::where('metier_id', $metierId)
        ->with(['formateur.user', 'salle.batiment'])
        ->get();

    \Log::info("📚 {$competences->count()} compétences trouvées pour le métier {$metier->intitule}");

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
                'pourcentage_utilise' => $quotaTotal > 0 ? round(($heuresPlanifiees / $quotaTotal) * 100, 1) : 0,
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
            'total_competences' => count($competencesAvecQuota),
            'total_quota_restant' => array_sum(array_column($competencesAvecQuota, 'heures_restantes'))
        ]
    ]);
}




public function update(Request $request, $id)
{
    $validated = $request->validate([
        'annee_id' => 'required|exists:annees,id',
        'heure_debut' => 'required|date_format:H:i:s',
        'heure_fin' => 'required|date_format:H:i:s|after:heure_debut',
        'date_debut' => 'required|date',
        'date_fin' => 'required|date|after_or_equal:date_debut',
        'competences' => 'nullable|array',
        'competences.*' => 'exists:competences,id'
    ]);

    // Trouver l'emploi du temps à modifier
    $emploi = EmploiDuTemps::with(['compemplois.competence'])->find($id);
    
    if (!$emploi) {
        return response()->json([
            'success' => false,
            'message' => 'Emploi du temps non trouvé.'
        ], 404);
    }

    // Vérifier les droits de modification
    if (!$this->verifierDroitsCreation($validated['annee_id'])) {
        return response()->json([
            'success' => false,
            'message' => 'Vous ne pouvez modifier que les créneaux de vos départements.'
        ], 403);
    }

    // Vérifier les conflits (en excluant l'emploi actuel)
    $conflits = $this->verifierConflitsModification($validated, $id);
    if (!empty($conflits)) {
        return response()->json([
            'success' => false,
            'message' => 'Conflits détectés',
            'conflicts' => $conflits
        ], 422);
    }

    DB::beginTransaction();
    try {
        // 1. Mettre à jour l'emploi du temps
        $emploi->update([
            'annee_id' => $validated['annee_id'],
            'heure_debut' => $validated['heure_debut'],
            'heure_fin' => $validated['heure_fin'],
            'date_debut' => $validated['date_debut'],
            'date_fin' => $validated['date_fin'],
        ]);

        \Log::info("✅ Emploi du temps {$emploi->id} mis à jour");

        // 2. Supprimer les anciennes associations compétences
        DB::table('compemplois')
            ->where('emploi_du_temps_id', $emploi->id)
            ->delete();

        \Log::info("🗑️ Anciennes compétences supprimées pour l'emploi {$emploi->id}");

        // 3. Ajouter les nouvelles compétences si fournies
        if (!empty($validated['competences'])) {
            \Log::info('🎯 Insertion de ' . count($validated['competences']) . ' nouvelles compétences');
            
            foreach ($validated['competences'] as $competenceId) {
                DB::table('compemplois')->insert([
                    'emploi_du_temps_id' => $emploi->id,
                    'competence_id' => $competenceId,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                \Log::info("📝 Nouvelle CompEmploi créée: Emploi {$emploi->id} ↔ Compétence {$competenceId}");
            }
            
            // Vérifier que les compemplois ont été créés
            $nombreCompEmplois = DB::table('compemplois')
                ->where('emploi_du_temps_id', $emploi->id)
                ->count();
            \Log::info("✅ {$nombreCompEmplois} nouvelles compemplois créées pour l'emploi {$emploi->id}");
        } else {
            \Log::warning("⚠️ AUCUNE compétence fournie pour la mise à jour de l'emploi {$emploi->id}");
        }

        DB::commit();

        // Recharger avec les relations
        $emploi = EmploiDuTemps::with([
            'annee',
            'compemplois.competence.formateur.user',
            'compemplois.competence.metier.departement',
            'compemplois.competence.salle'
        ])->find($emploi->id);

        \Log::info("🔄 Emploi rechargé avec " . $emploi->compemplois->count() . " compemplois");

        return response()->json([
            'success' => true,
            'message' => 'Emploi du temps mis à jour avec succès',
            'data' => $emploi
        ], 200);

    } catch (\Exception $e) {
        DB::rollback();
        \Log::error("❌ Erreur mise à jour emploi du temps: " . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
        ], 500);
    }
}

private function verifierConflitsModification($data, $emploiId)
{
    $conflicts = [];

    // Vérifier conflit année (exclure l'emploi actuel)
    $conflitAnnee = EmploiDuTemps::where('annee_id', $data['annee_id'])
        ->where('id', '!=', $emploiId) // Exclure l'emploi actuel
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

    // Vérifier conflit formateur si des compétences sont fournies
    if (isset($data['competences']) && !empty($data['competences'])) {
        foreach ($data['competences'] as $competenceId) {
            $competence = Competence::find($competenceId);
            if ($competence && $competence->formateur_id) {
                $conflitFormateur = EmploiDuTemps::where('date_debut', $data['date_debut'])
                    ->where('id', '!=', $emploiId) // Exclure l'emploi actuel
                    ->whereHas('compemplois', function($q) use ($competence) {
                        $q->whereHas('competence', function($compQuery) use ($competence) {
                            $compQuery->where('formateur_id', $competence->formateur_id);
                        });
                    })
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
                    $conflicts[] = "Le formateur de la compétence {$competence->nom} a déjà un cours à cette heure";
                }
            }
        }
    }

    return $conflicts;
}

private function verifierConflitCreneau($anneeId, $date, $indexCreneau, $dureeHeures, $formateurId = null)
{
    $creneauxCours = $this->genererCreneauxPourDuree($indexCreneau, $dureeHeures);
    
    if (empty($creneauxCours)) {
        return true; // Conflit si impossible de générer les créneaux
    }

    $heureDebut = $this->creneauxHoraires[$creneauxCours[0]]['debut'] . ':00';
    $heureFin = $this->creneauxHoraires[end($creneauxCours)]['fin'] . ':00';

    // 1. Conflit année
    $conflitAnnee = EmploiDuTemps::where('annee_id', $anneeId)
        ->where('date_debut', $date)
        ->where(function($query) use ($heureDebut, $heureFin) {
            $query->whereBetween('heure_debut', [$heureDebut, $heureFin])
                  ->orWhereBetween('heure_fin', [$heureDebut, $heureFin])
                  ->orWhere(function($q) use ($heureDebut, $heureFin) {
                      $q->where('heure_debut', '<=', $heureDebut)
                        ->where('heure_fin', '>=', $heureFin);
                  });
        })
        ->exists();

    if ($conflitAnnee) {
        return true; // Conflit détecté
    }

    // 2. Conflit formateur
    if ($formateurId) {
        $conflitFormateur = EmploiDuTemps::where('date_debut', $date)
            ->whereHas('compemplois', function($q) use ($formateurId) {
                $q->whereHas('competence', function($compQuery) use ($formateurId) {
                    $compQuery->where('formateur_id', $formateurId);
                });
            })
            ->where(function($query) use ($heureDebut, $heureFin) {
                $query->whereBetween('heure_debut', [$heureDebut, $heureFin])
                      ->orWhereBetween('heure_fin', [$heureDebut, $heureFin])
                      ->orWhere(function($q) use ($heureDebut, $heureFin) {
                          $q->where('heure_debut', '<=', $heureDebut)
                            ->where('heure_fin', '>=', $heureFin);
                      });
            })
            ->exists();

        if ($conflitFormateur) {
            return true; // Conflit détecté
        }
    }

    return false; // Pas de conflit
}


public function previewLimitation(Request $request)
{
    $validated = $request->validate([
        'competences' => 'required|array|min:1',
        'competences.*.id' => 'required|exists:competences,id',
        'competences.*.duree_cours' => 'required|integer|min:1|max:4',
        'max_seances_par_competence' => 'required|integer|min:1|max:10'
    ]);

    $preview = [];
    $totalHeuresUtilisees = 0;
    $totalHeuresRestantes = 0;

    foreach ($validated['competences'] as $configCompetence) {
        $competence = Competence::find($configCompetence['id']);
        
        if ($competence) {
            $dureeCoursHeures = $configCompetence['duree_cours'];
            $quotaRestant = floatval($competence->quota_horaire ?? 0);
            
            $heuresPlanifiees = $this->calculerHeuresPlanifiees($competence->id);
            $heuresRestantes = max(0, $quotaRestant - $heuresPlanifiees);
            
            $maxSeancesPossibles = floor($heuresRestantes / $dureeCoursHeures);
            $seancesACréer = min($maxSeancesPossibles, $validated['max_seances_par_competence']);
            
            $heuresUtilisees = $seancesACréer * $dureeCoursHeures;
            $heuresRestantesApres = $heuresRestantes - $heuresUtilisees;
            
            $totalHeuresUtilisees += $heuresUtilisees;
            $totalHeuresRestantes += $heuresRestantesApres;

            $preview[] = [
                'competence_id' => $competence->id,
                'nom' => $competence->nom,
                'quota_total' => $quotaRestant,
                'heures_deja_planifiees' => $heuresPlanifiees,
                'heures_restantes_avant' => $heuresRestantes,
                'duree_par_cours' => $dureeCoursHeures,
                'max_seances_possibles' => $maxSeancesPossibles,
                'seances_a_creer' => $seancesACréer,
                'heures_a_utiliser' => $heuresUtilisees,
                'heures_restantes_apres' => $heuresRestantesApres
            ];
        }
    }

    return response()->json([
        'success' => true,
        'data' => [
            'competences' => $preview,
            'resume' => [
                'total_heures_utilisees' => $totalHeuresUtilisees,
                'total_heures_restantes' => $totalHeuresRestantes,
                'nombre_competences' => count($preview)
            ]
        ]
    ]);
}

public function verifierDisponibilite(Request $request)
{
    $validated = $request->validate([
        'formateur_ids' => 'required|array',
        'formateur_ids.*' => 'exists:formateurs,id',
        'date_debut' => 'required|date',
        'date_fin' => 'required|date|after_or_equal:date_debut'
    ]);

    $disponibilites = [];

    foreach ($validated['formateur_ids'] as $formateurId) {
        $formateur = Formateur::with('user')->find($formateurId);
        
        if ($formateur) {
            $emploisExistants = EmploiDuTemps::whereHas('compemplois', function($q) use ($formateurId) {
                $q->whereHas('competence', function($compQuery) use ($formateurId) {
                    $compQuery->where('formateur_id', $formateurId);
                });
            })
            ->whereBetween('date_debut', [$validated['date_debut'], $validated['date_fin']])
            ->orderBy('date_debut')
            ->orderBy('heure_debut')
            ->get(['date_debut', 'heure_debut', 'heure_fin']);

            $disponibilites[] = [
                'formateur_id' => $formateurId,
                'nom_complet' => $formateur->user->prenom . ' ' . $formateur->user->nom,
                'emplois_existants' => $emploisExistants->count(),
                'creneaux_occupes' => $emploisExistants
            ];
        }
    }

    return response()->json([
        'success' => true,
        'data' => $disponibilites
    ]);
}

public function deplacerCours(Request $request, $id)
{
    $validated = $request->validate([
        'nouvelle_date' => 'required|date',
        'nouvelle_heure_debut' => 'required|date_format:H:i:s',
        'nouvelle_heure_fin' => 'required|date_format:H:i:s|after:nouvelle_heure_debut',
        'raison_deplacement' => 'nullable|string|max:255',
    ]);

    // Trouver l'emploi du temps à déplacer
    $emploi = EmploiDuTemps::with(['compemplois.competence.formateur', 'annee'])->find($id);
    
    if (!$emploi) {
        return response()->json([
            'success' => false,
            'message' => 'Cours non trouvé.'
        ], 404);
    }

    // Vérifier les droits de modification
    if (!$this->verifierDroitsCreation($emploi->annee_id)) {
        return response()->json([
            'success' => false,
            'message' => 'Vous ne pouvez déplacer que les cours de vos départements.'
        ], 403);
    }

    // 🔍 VÉRIFIER LES CONFLITS POUR LE NOUVEAU CRÉNEAU
    $conflits = $this->verifierConflitsDeplacement($emploi, $validated);
    if (!empty($conflits)) {
        return response()->json([
            'success' => false,
            'message' => 'Impossible de déplacer le cours',
            'conflicts' => $conflits,
            'suggestions' => $this->proposerCreneauxAlternatifs($emploi, $validated)
        ], 422);
    }

    DB::beginTransaction();
    try {
        // 📝 SAUVEGARDER L'ANCIEN CRÉNEAU POUR L'HISTORIQUE
        $ancienCreneau = [
            'date' => $emploi->date_debut,
            'heure_debut' => $emploi->heure_debut,
            'heure_fin' => $emploi->heure_fin,
        ];

        // 🎯 METTRE À JOUR AVEC LE NOUVEAU CRÉNEAU
        $emploi->update([
            'date_debut' => $validated['nouvelle_date'],
            'date_fin' => $validated['nouvelle_date'], // Même jour
            'heure_debut' => $validated['nouvelle_heure_debut'],
            'heure_fin' => $validated['nouvelle_heure_fin'],
        ]);

        // 📋 ENREGISTRER L'HISTORIQUE DU DÉPLACEMENT
        $this->enregistrerHistoriqueDeplacement($emploi, $ancienCreneau, $validated);

        DB::commit();

        // 🔄 RECHARGER AVEC LES RELATIONS
        $emploi->load([
            'annee',
            'compemplois.competence.formateur.user',
            'compemplois.competence.metier.departement',
            'compemplois.competence.salle.batiment'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cours déplacé avec succès !',
            'data' => [
                'emploi' => $emploi,
                'ancien_creneau' => $ancienCreneau,
                'nouveau_creneau' => [
                    'date' => $validated['nouvelle_date'],
                    'heure_debut' => $validated['nouvelle_heure_debut'],
                    'heure_fin' => $validated['nouvelle_heure_fin'],
                ],
                'details_deplacement' => $this->getDetailsDeplacement($ancienCreneau, $validated)
            ]
        ], 200);

    } catch (\Exception $e) {
        DB::rollback();
        \Log::error("❌ Erreur déplacement cours: " . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors du déplacement: ' . $e->getMessage()
        ], 500);
    }
}

private function verifierConflitsDeplacement($emploi, $nouveauCreneau)
{
    $conflicts = [];

    // 1. Conflit avec d'autres cours de la même année
    $conflitAnnee = EmploiDuTemps::where('annee_id', $emploi->annee_id)
        ->where('id', '!=', $emploi->id) // Exclure le cours actuel
        ->where('date_debut', $nouveauCreneau['nouvelle_date'])
        ->where(function($query) use ($nouveauCreneau) {
            $query->whereBetween('heure_debut', [
                $nouveauCreneau['nouvelle_heure_debut'], 
                $nouveauCreneau['nouvelle_heure_fin']
            ])
            ->orWhereBetween('heure_fin', [
                $nouveauCreneau['nouvelle_heure_debut'], 
                $nouveauCreneau['nouvelle_heure_fin']
            ])
            ->orWhere(function($q) use ($nouveauCreneau) {
                $q->where('heure_debut', '<=', $nouveauCreneau['nouvelle_heure_debut'])
                  ->where('heure_fin', '>=', $nouveauCreneau['nouvelle_heure_fin']);
            });
        })
        ->exists();

    if ($conflitAnnee) {
        $conflicts[] = [
            'type' => 'annee',
            'message' => "La classe a déjà un cours à ce créneau"
        ];
    }

    // 2. Conflit avec les formateurs de ce cours
    foreach ($emploi->compemplois as $compEmploi) {
        $competence = $compEmploi->competence;
        if ($competence && $competence->formateur_id) {
            $conflitFormateur = EmploiDuTemps::where('date_debut', $nouveauCreneau['nouvelle_date'])
                ->where('id', '!=', $emploi->id)
                ->whereHas('compemplois', function($q) use ($competence) {
                    $q->whereHas('competence', function($compQuery) use ($competence) {
                        $compQuery->where('formateur_id', $competence->formateur_id);
                    });
                })
                ->where(function($query) use ($nouveauCreneau) {
                    $query->whereBetween('heure_debut', [
                        $nouveauCreneau['nouvelle_heure_debut'], 
                        $nouveauCreneau['nouvelle_heure_fin']
                    ])
                    ->orWhereBetween('heure_fin', [
                        $nouveauCreneau['nouvelle_heure_debut'], 
                        $nouveauCreneau['nouvelle_heure_fin']
                    ])
                    ->orWhere(function($q) use ($nouveauCreneau) {
                        $q->where('heure_debut', '<=', $nouveauCreneau['nouvelle_heure_debut'])
                          ->where('heure_fin', '>=', $nouveauCreneau['nouvelle_heure_fin']);
                    });
                })
                ->exists();

            if ($conflitFormateur) {
                $nomFormateur = $competence->formateur->user->prenom . ' ' . $competence->formateur->user->nom;
                $conflicts[] = [
                    'type' => 'formateur',
                    'message' => "Le formateur {$nomFormateur} a déjà un cours à ce créneau",
                    'formateur' => $nomFormateur,
                    'competence' => $competence->nom
                ];
            }
        }
    }

    // 3. Vérification des heures d'ouverture
    $heureDebut = (int) substr($nouveauCreneau['nouvelle_heure_debut'], 0, 2);
    $heureFin = (int) substr($nouveauCreneau['nouvelle_heure_fin'], 0, 2);

    if ($heureDebut < 8 || $heureFin > 17) {
        $conflicts[] = [
            'type' => 'horaire',
            'message' => "Les cours doivent se dérouler entre 8h et 17h"
        ];
    }

    // 4. Vérification pause déjeuner (13h-14h)
    if ($heureDebut <= 13 && $heureFin >= 14) {
        $conflicts[] = [
            'type' => 'pause',
            'message' => "Le cours ne peut pas chevaucher la pause déjeuner (13h-14h)"
        ];
    }

    return $conflicts;
}

private function proposerCreneauxAlternatifs($emploi, $creneauDemande)
{
    $suggestions = [];
    $dateRecherche = Carbon::parse($creneauDemande['nouvelle_date']);
    
    // Calculer la durée du cours
    $heureDebutOriginale = Carbon::parse($creneauDemande['nouvelle_heure_debut']);
    $heureFinOriginale = Carbon::parse($creneauDemande['nouvelle_heure_fin']);
    $dureeMinutes = $heureFinOriginale->diffInMinutes($heureDebutOriginale);

    // Chercher des créneaux libres dans les 7 prochains jours
    for ($jour = 0; $jour < 7; $jour++) {
        $dateTest = $dateRecherche->copy()->addDays($jour);
        
        // Éviter le dimanche
        if ($dateTest->dayOfWeek == 0) continue;

        // Tester différents créneaux horaires
        for ($heure = 8; $heure <= 16; $heure++) {
            $heureDebutTest = sprintf('%02d:00:00', $heure);
            $heureFinTest = Carbon::parse($heureDebutTest)->addMinutes($dureeMinutes)->format('H:i:s');
            
            // Éviter la pause déjeuner
            if ($heure <= 13 && Carbon::parse($heureFinTest)->hour >= 14) continue;
            if (Carbon::parse($heureFinTest)->hour > 17) continue;

            // Tester les conflits
            $conflitsTest = $this->verifierConflitsDeplacement($emploi, [
                'nouvelle_date' => $dateTest->format('Y-m-d'),
                'nouvelle_heure_debut' => $heureDebutTest,
                'nouvelle_heure_fin' => $heureFinTest,
            ]);

            if (empty($conflitsTest)) {
                $suggestions[] = [
                    'date' => $dateTest->format('Y-m-d'),
                    'jour_nom' => $this->joursSemaine[$dateTest->dayOfWeek - 1],
                    'heure_debut' => $heureDebutTest,
                    'heure_fin' => $heureFinTest,
                    'score' => $this->calculerScoreCreneau($dateTest, $heure, $dateRecherche)
                ];

                // Limiter à 5 suggestions
                if (count($suggestions) >= 5) break 2;
            }
        }
    }

    // Trier par score (plus proche = meilleur)
    usort($suggestions, function($a, $b) {
        return $a['score'] - $b['score'];
    });

    return $suggestions;
}




/**
 * 📊 CALCULER LE SCORE D'UN CRÉNEAU (plus proche = mieux)
 */
private function calculerScoreCreneau($dateTest, $heureTest, $dateOriginale)
{
    $score = 0;
    
    // Distance en jours
    $score += abs($dateTest->diffInDays($dateOriginale)) * 10;
    
    // Préférence pour les heures du matin
    if ($heureTest >= 8 && $heureTest <= 11) {
        $score += 0; // Bonus matin
    } elseif ($heureTest >= 14 && $heureTest <= 16) {
        $score += 2; // Après-midi acceptable
    } else {
        $score += 5; // Fin de journée moins idéale
    }
    
    return $score;
}


    /**
     * Vérifier les conflits avant création
     */
   /**
 * Vérifier les conflits avant création
 */
private function verifierConflitsCreation($data)
{
    $conflicts = [];

    // Vérifier conflit année (une année ne peut avoir qu'un cours à la fois)
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

    // 👈 CORRECTION : Vérifier si les compétences existent avant de les traiter
    if (isset($data['competences']) && !empty($data['competences'])) {
        // Vérifier conflit formateur (via les compétences)
        foreach ($data['competences'] as $competenceId) {
            $competence = Competence::find($competenceId);
            if ($competence && $competence->formateur_id) {
                $conflitFormateur = EmploiDuTemps::where('date_debut', $data['date_debut'])
                    ->whereHas('compemplois', function($q) use ($competence) {
                        $q->whereHas('competence', function($compQuery) use ($competence) {
                            $compQuery->where('formateur_id', $competence->formateur_id);
                        });
                    })
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
                    $conflicts[] = "Le formateur de la compétence {$competence->nom} a déjà un cours à cette heure";
                }
            }
        }
    } 

    return $conflicts;
}
}