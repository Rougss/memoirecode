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

class EmploiDuTempsController extends Controller
{
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
public function index(Request $request)
{
    $user = Auth::user();
    $formateur = Formateur::where('user_id', $user->id)->first();

    if (!$formateur) {
        return response()->json([
            'success' => false,
            'message' => 'Utilisateur non trouvé en tant que formateur.'
        ], 404);
    }

    // Récupérer les départements gérés par ce formateur (en tant que chef)
    $departementsGeres = Departement::where('formateur_id', $formateur->id)->get();
    
    if ($departementsGeres->isEmpty()) {
        return response()->json([
            'success' => false,
            'message' => 'Vous ne gérez aucun département.'
        ], 403);
    }

    // 👈 CORRECTION : Relations correctes selon votre diagramme
    $emplois = EmploiDuTemps::with([
        'annee',  // Juste l'année
        'compemplois.competence.formateur.user',
        'compemplois.competence.metier.departement',  // 👈 Département via métier
        'compemplois.competence.salle'
    ])->orderBy('date_debut')
    ->orderBy('heure_debut')
    ->whereHas('compemplois')
    ->get();

    // Formatter les données pour le frontend
    $emploisFormates = $emplois->map(function($emploi) {
        // 👈 Récupérer le département via les compétences
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
                'annee' => $emploi->annee->annee,
                // 👈 Département récupéré via les compétences
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
                    'formateur' => $competence->formateur ? [
                        'id' => $competence->formateur->id,
                        'nom' => $competence->formateur->user->nom,
                        'prenom' => $competence->formateur->user->prenom
                    ] : null,
                    'metier' => $competence->metier ? [
                        'id' => $competence->metier->id,
                        'intitule' => $competence->metier->intitule
                    ] : null,
                    'salle' => $competence->salle ? [
                        'id' => $competence->salle->id,
                        'intitule' => $competence->salle->intitule
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