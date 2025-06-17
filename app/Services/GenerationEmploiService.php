<?php

namespace App\Services;

use App\Models\EmploiDuTemps;
use App\Models\Formateur;
use App\Models\Departement;
use App\Models\Salle;
use App\Models\Annee;
use App\Models\Competence;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GenerationEmploiService
{
    // Créneaux horaires standards
    private $creneauxHoraires = [
        ['debut' => '08:00:00', 'fin' => '10:00:00'],
        ['debut' => '10:15:00', 'fin' => '12:15:00'],
        ['debut' => '14:00:00', 'fin' => '16:00:00'],
        ['debut' => '16:15:00', 'fin' => '18:15:00'],
    ];

    /**
     * Génère automatiquement l'emploi du temps pour un département
     */
    public function genererEmploiDuTemps($departementId, $dateDebut, $dateFin)
    {
        $departement = Departement::with([
            'chefDepartement.user',
            'formateurs.competences.metier', // Via table formadepart
            'batiment.salles',
            'metiers.competences'
        ])->find($departementId);

        if (!$departement) {
            throw new \Exception("Département non trouvé");
        }

        // Récupérer les données nécessaires
        $annees = Annee::where('departement_id', $departementId)->get();
        $formateurs = $departement->formateurs; // Via table formadepart
        $salles = $departement->batiment->salles;

        $emploisGeneres = [];
        $currentDate = Carbon::parse($dateDebut);
        $endDate = Carbon::parse($dateFin);

        while ($currentDate->lte($endDate)) {
            // Ignorer les week-ends
            if (!$currentDate->isWeekend()) {
                $emploisDuJour = $this->genererEmploisPourUnJour(
                    $currentDate->toDateString(),
                    $annees,
                    $formateurs,
                    $salles,
                    $departement
                );
                $emploisGeneres = array_merge($emploisGeneres, $emploisDuJour);
            }
            $currentDate->addDay();
        }

        // Sauvegarder en base
        DB::beginTransaction();
        try {
            foreach ($emploisGeneres as $emploiData) {
                // Créer l'emploi du temps
                $emploi = EmploiDuTemps::create([
                    'annee_id' => $emploiData['annee_id'],
                    'heure_debut' => $emploiData['heure_debut'],
                    'heure_fin' => $emploiData['heure_fin'],
                    'date_debut' => $emploiData['date_debut'],
                    'date_fin' => $emploiData['date_fin'],
                ]);

                // Associer les compétences via compemplois
                foreach ($emploiData['competences'] as $competenceId) {
                    DB::table('compemplois')->insert([
                        'emploi_du_temps_id' => $emploi->id,
                        'competence_id' => $competenceId,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }

        return [
            'success' => true,
            'message' => count($emploisGeneres) . ' créneaux générés automatiquement',
            'data' => $emploisGeneres
        ];
    }

    /**
     * Génère les emplois pour une journée donnée
     */
    private function genererEmploisPourUnJour($date, $annees, $formateurs, $salles, $departement)
    {
        $emploisDuJour = [];
        $ressourcesOccupees = $this->getRessourcesOccupeesParJour($date);

        foreach ($this->creneauxHoraires as $creneau) {
            foreach ($annees as $annee) {
                // Vérifier si l'année est déjà occupée à ce créneau
                if ($this->estAnneeOccupee($ressourcesOccupees, $annee->id, $creneau, $date)) {
                    continue;
                }

                // Algorithme de sélection des compétences et formateurs
                $competencesSelectionnees = $this->selectionnerCompetencesPourCreneau(
                    $formateurs,
                    $annee,
                    $creneau,
                    $date,
                    $ressourcesOccupees,
                    $departement
                );

                if (!empty($competencesSelectionnees)) {
                    // Créer le créneau
                    $nouveauCreneau = [
                        'annee_id' => $annee->id,
                        'date_debut' => $date,
                        'date_fin' => $date,
                        'heure_debut' => $creneau['debut'],
                        'heure_fin' => $creneau['fin'],
                        'competences' => $competencesSelectionnees
                    ];

                    $emploisDuJour[] = $nouveauCreneau;

                    // Marquer les ressources comme occupées
                    $this->marquerRessourcesOccupees($ressourcesOccupees, $competencesSelectionnees, $creneau, $date, $annee->id);
                }
            }
        }

        return $emploisDuJour;
    }

    /**
     * Sélectionne les compétences et formateurs pour un créneau
     * Logique : Privilégier la diversité des matières et l'équité entre formateurs
     */
    private function selectionnerCompetencesPourCreneau($formateurs, $annee, $creneau, $date, &$ressourcesOccupees, $departement)
    {
        $competencesSelectionnees = [];
        $maxCompetencesParCreneau = 2; // Limite de compétences par créneau

        // Récupérer les compétences disponibles pour ce département
        $competencesDisponibles = Competence::whereHas('metier', function($q) use ($departement) {
            $q->where('departement_id', $departement->id);
        })->get();

        // Filtrer les formateurs disponibles
        $formateursDisponibles = $formateurs->filter(function($formateur) use ($ressourcesOccupees, $creneau, $date) {
            return !$this->estFormateurOccupe($ressourcesOccupees, $formateur->id, $creneau, $date);
        });

        if ($formateursDisponibles->isEmpty()) {
            return [];
        }

        // Algorithme de sélection intelligente
        foreach ($formateursDisponibles->take($maxCompetencesParCreneau) as $formateur) {
            // Trouver les compétences de ce formateur pour ce département
            $competencesFormateur = $competencesDisponibles->where('formateur_id', $formateur->id);

            if ($competencesFormateur->isNotEmpty()) {
                // Sélectionner une compétence (priorité aux moins enseignées récemment)
                $competenceChoisie = $this->choisirMeilleureCompetence($competencesFormateur, $date);
                
                if ($competenceChoisie) {
                    $competencesSelectionnees[] = $competenceChoisie->id;
                }
            }
        }

        return $competencesSelectionnees;
    }

    /**
     * Choisit la meilleure compétence selon les critères pédagogiques
     */
    private function choisirMeilleureCompetence($competences, $date)
    {
        // Logique simple : rotation des compétences
        // On peut améliorer avec des algorithmes plus sophistiqués
        
        // Calculer la fréquence d'enseignement récente de chaque compétence
        $competencesAvecScore = $competences->map(function($competence) use ($date) {
            // Compter les occurrences dans les 7 derniers jours
            $occurrencesRecentes = DB::table('compemplois')
                ->join('emploi_du_temps', 'compemplois.emploi_du_temps_id', '=', 'emploi_du_temps.id')
                ->where('compemplois.competence_id', $competence->id)
                ->where('emploi_du_temps.date_debut', '>=', Carbon::parse($date)->subDays(7))
                ->where('emploi_du_temps.date_debut', '<', $date)
                ->count();

            return [
                'competence' => $competence,
                'score' => $occurrencesRecentes // Plus le score est bas, plus prioritaire
            ];
        });

        // Prendre la compétence avec le score le plus bas (moins enseignée récemment)
        $meilleureCompetence = $competencesAvecScore->sortBy('score')->first();

        return $meilleureCompetence ? $meilleureCompetence['competence'] : null;
    }

    /**
     * Récupère les ressources déjà occupées pour une date donnée
     */
    private function getRessourcesOccupeesParJour($date)
    {
        $emploisExistants = EmploiDuTemps::with('competences')
            ->where('date_debut', $date)
            ->get();

        $occupees = [
            'formateurs' => [],
            'annees' => [],
            'salles' => []
        ];

        foreach ($emploisExistants as $emploi) {
            $creneau = [
                'debut' => $emploi->heure_debut,
                'fin' => $emploi->heure_fin
            ];
            
            // Marquer l'année comme occupée
            $cleAnnee = $this->genererCleRessource('annee', $emploi->annee_id, $creneau, $date);
            $occupees['annees'][$cleAnnee] = true;

            // Marquer les formateurs comme occupés
            foreach ($emploi->competences as $competence) {
                if ($competence->formateur_id) {
                    $cleFormateur = $this->genererCleRessource('formateur', $competence->formateur_id, $creneau, $date);
                    $occupees['formateurs'][$cleFormateur] = true;
                }
            }
        }

        return $occupees;
    }

    /**
     * Vérifie si une année est occupée
     */
    private function estAnneeOccupee($ressourcesOccupees, $anneeId, $creneau, $date)
    {
        $cle = $this->genererCleRessource('annee', $anneeId, $creneau, $date);
        return isset($ressourcesOccupees['annees'][$cle]);
    }

    /**
     * Vérifie si un formateur est occupé
     */
    private function estFormateurOccupe($ressourcesOccupees, $formateurId, $creneau, $date)
    {
        $cle = $this->genererCleRessource('formateur', $formateurId, $creneau, $date);
        return isset($ressourcesOccupees['formateurs'][$cle]);
    }

    /**
     * Marque les ressources comme occupées
     */
    private function marquerRessourcesOccupees(&$ressourcesOccupees, $competences, $creneau, $date, $anneeId)
    {
        // Marquer l'année
        $cleAnnee = $this->genererCleRessource('annee', $anneeId, $creneau, $date);
        $ressourcesOccupees['annees'][$cleAnnee] = true;

        // Marquer les formateurs
        foreach ($competences as $competenceId) {
            $competence = Competence::find($competenceId);
            if ($competence && $competence->formateur_id) {
                $cleFormateur = $this->genererCleRessource('formateur', $competence->formateur_id, $creneau, $date);
                $ressourcesOccupees['formateurs'][$cleFormateur] = true;
            }
        }
    }

    /**
     * Génère une clé unique pour une ressource
     */
    private function genererCleRessource($type, $id, $creneau, $date)
    {
        return "{$date}_{$creneau['debut']}_{$creneau['fin']}_{$type}_{$id}";
    }

    /**
     * Analyse et optimise un emploi du temps existant
     */
    public function analyserEmploiDuTemps($departementId, $dateDebut, $dateFin)
    {
        $emplois = EmploiDuTemps::with(['annee', 'competences.formateur.user'])
            ->whereHas('annee', function($q) use ($departementId) {
                $q->where('departement_id', $departementId);
            })
            ->whereBetween('date_debut', [$dateDebut, $dateFin])
            ->get();

        $statistiques = [
            'total_creneaux' => $emplois->count(),
            'repartition_formateurs' => [],
            'taux_occupation_salles' => [],
            'conflits_detectes' => [],
            'suggestions_optimisation' => []
        ];

        // Analyser la répartition par formateur
        $repartitionFormateurs = [];
        foreach ($emplois as $emploi) {
            foreach ($emploi->competences as $competence) {
                if ($competence->formateur) {
                    $nom = $competence->formateur->user->nom . ' ' . $competence->formateur->user->prenom;
                    $repartitionFormateurs[$nom] = ($repartitionFormateurs[$nom] ?? 0) + 1;
                }
            }
        }
        $statistiques['repartition_formateurs'] = $repartitionFormateurs;

        // Détecter les déséquilibres et suggestions
        $moyenneCreneauxParFormateur = array_sum($repartitionFormateurs) / count($repartitionFormateurs);
        
        foreach ($repartitionFormateurs as $formateur => $nbCreneaux) {
            if ($nbCreneaux > $moyenneCreneauxParFormateur * 1.5) {
                $statistiques['suggestions_optimisation'][] = "Formateur $formateur surchargé ($nbCreneaux créneaux)";
            } elseif ($nbCreneaux < $moyenneCreneauxParFormateur * 0.5) {
                $statistiques['suggestions_optimisation'][] = "Formateur $formateur sous-utilisé ($nbCreneaux créneaux)";
            }
        }

        // Détecter les conflits
        $statistiques['conflits_detectes'] = $this->detecterConflits($emplois);

        return $statistiques;
    }

    /**
     * Détecte les conflits dans l'emploi du temps
     */
    private function detecterConflits($emplois)
    {
        $conflits = [];
        
        foreach ($emplois as $emploi1) {
            foreach ($emplois as $emploi2) {
                if ($emploi1->id >= $emploi2->id) continue;
                
                // Même date et heures qui se chevauchent
                if ($emploi1->date_debut == $emploi2->date_debut &&
                    $this->heuresSeChevauchet($emploi1, $emploi2)) {
                    
                    // Conflit d'année
                    if ($emploi1->annee_id == $emploi2->annee_id) {
                        $conflits[] = [
                            'type' => 'annee',
                            'message' => "Année {$emploi1->annee->intitule} a 2 cours simultanés",
                            'emploi1_id' => $emploi1->id,
                            'emploi2_id' => $emploi2->id
                        ];
                    }
                    
                    // Conflit de formateur
                    $formateurs1 = $emploi1->competences->pluck('formateur_id')->filter();
                    $formateurs2 = $emploi2->competences->pluck('formateur_id')->filter();
                    $formateursCommuns = $formateurs1->intersect($formateurs2);
                    
                    foreach ($formateursCommuns as $formateurId) {
                        $formateur = \App\Models\Formateur::with('user')->find($formateurId);
                        $conflits[] = [
                            'type' => 'formateur',
                            'message' => "Formateur {$formateur->user->nom} a 2 cours simultanés",
                            'emploi1_id' => $emploi1->id,
                            'emploi2_id' => $emploi2->id
                        ];
                    }
                }
            }
        }
        
        return $conflits;
    }

    /**
     * Vérifie si deux créneaux horaires se chevauchent
     */
    private function heuresSeChevauchet($emploi1, $emploi2)
    {
        $debut1 = strtotime($emploi1->heure_debut);
        $fin1 = strtotime($emploi1->heure_fin);
        $debut2 = strtotime($emploi2->heure_debut);
        $fin2 = strtotime($emploi2->heure_fin);
        
        return !($fin1 <= $debut2 || $debut1 >= $fin2);
    }

    /**
     * Génère un rapport d'occupation pour le département
     */
    public function genererRapportOccupation($departementId, $dateDebut, $dateFin)
    {
        $departement = Departement::with(['batiment.salles', 'formateurs'])->find($departementId);
        $emplois = EmploiDuTemps::with(['competences.formateur', 'competences.salle'])
            ->whereHas('annee', function($q) use ($departementId) {
                $q->where('departement_id', $departementId);
            })
            ->whereBetween('date_debut', [$dateDebut, $dateFin])
            ->get();

        $rapport = [
            'periode' => ['debut' => $dateDebut, 'fin' => $dateFin],
            'departement' => $departement->nom_departement,
            'occupation_salles' => [],
            'charge_formateurs' => [],
            'utilisation_creneaux' => [],
            'recommandations' => []
        ];

        // Analyser l'occupation des salles
        $totalCreneauxPossibles = $this->calculerTotalCreneauxPossibles($dateDebut, $dateFin);
        foreach ($departement->batiment->salles as $salle) {
            $creneauxOccupes = 0;
            foreach ($emplois as $emploi) {
                foreach ($emploi->competences as $competence) {
                    if ($competence->salle && $competence->salle->id == $salle->id) {
                        $creneauxOccupes++;
                    }
                }
            }
            
            $tauxOccupation = ($creneauxOccupes / $totalCreneauxPossibles) * 100;
            $rapport['occupation_salles'][] = [
                'salle' => $salle->intitule,
                'taux_occupation' => round($tauxOccupation, 2),
                'creneaux_occupes' => $creneauxOccupes,
                'creneaux_possibles' => $totalCreneauxPossibles
            ];
        }

        // Analyser la charge des formateurs
        foreach ($departement->formateurs as $formateur) {
            $nbCreneaux = 0;
            foreach ($emplois as $emploi) {
                foreach ($emploi->competences as $competence) {
                    if ($competence->formateur_id == $formateur->id) {
                        $nbCreneaux++;
                    }
                }
            }
            
            $rapport['charge_formateurs'][] = [
                'formateur' => $formateur->user->nom . ' ' . $formateur->user->prenom,
                'nb_creneaux' => $nbCreneaux,
                'charge_hebdomadaire' => round($nbCreneaux / $this->calculerNombreSemaines($dateDebut, $dateFin), 1)
            ];
        }

        // Analyser l'utilisation des créneaux horaires
        $utilisationCreneaux = [];
        foreach ($this->creneauxHoraires as $creneau) {
            $utilisationCreneaux[$creneau['debut']] = 0;
        }
        
        foreach ($emplois as $emploi) {
            $utilisationCreneaux[$emploi->heure_debut] = ($utilisationCreneaux[$emploi->heure_debut] ?? 0) + 1;
        }
        
        $rapport['utilisation_creneaux'] = $utilisationCreneaux;

        // Générer des recommandations
        $rapport['recommandations'] = $this->genererRecommandations($rapport);

        return $rapport;
    }

    /**
     * Calcule le nombre total de créneaux possibles sur une période
     */
    private function calculerTotalCreneauxPossibles($dateDebut, $dateFin)
    {
        $debut = Carbon::parse($dateDebut);
        $fin = Carbon::parse($dateFin);
        $joursOuvrables = 0;
        
        while ($debut->lte($fin)) {
            if (!$debut->isWeekend()) {
                $joursOuvrables++;
            }
            $debut->addDay();
        }
        
        return $joursOuvrables * count($this->creneauxHoraires);
    }

    /**
     * Calcule le nombre de semaines dans une période
     */
    private function calculerNombreSemaines($dateDebut, $dateFin)
    {
        $debut = Carbon::parse($dateDebut);
        $fin = Carbon::parse($dateFin);
        return $debut->diffInWeeks($fin) + 1;
    }

    /**
     * Génère des recommandations d'optimisation
     */
    private function genererRecommandations($rapport)
    {
        $recommandations = [];
        
        // Recommandations sur l'occupation des salles
        foreach ($rapport['occupation_salles'] as $salle) {
            if ($salle['taux_occupation'] < 30) {
                $recommandations[] = "Salle {$salle['salle']} sous-utilisée ({$salle['taux_occupation']}%). Considérer une réorganisation.";
            } elseif ($salle['taux_occupation'] > 90) {
                $recommandations[] = "Salle {$salle['salle']} sur-utilisée ({$salle['taux_occupation']}%). Risque de saturation.";
            }
        }
        
        // Recommandations sur la charge des formateurs
        $charges = array_column($rapport['charge_formateurs'], 'charge_hebdomadaire');
        $moyenneCharge = array_sum($charges) / count($charges);
        
        foreach ($rapport['charge_formateurs'] as $formateur) {
            if ($formateur['charge_hebdomadaire'] > $moyenneCharge * 1.5) {
                $recommandations[] = "Formateur {$formateur['formateur']} surchargé ({$formateur['charge_hebdomadaire']} h/sem). Redistribuer la charge.";
            } elseif ($formateur['charge_hebdomadaire'] < $moyenneCharge * 0.5) {
                $recommandations[] = "Formateur {$formateur['formateur']} sous-utilisé ({$formateur['charge_hebdomadaire']} h/sem). Potentiel d'optimisation.";
            }
        }
        
        // Recommandations sur les créneaux horaires
        $utilisationMoyenne = array_sum($rapport['utilisation_creneaux']) / count($rapport['utilisation_creneaux']);
        foreach ($rapport['utilisation_creneaux'] as $heure => $utilisation) {
            if ($utilisation < $utilisationMoyenne * 0.5) {
                $recommandations[] = "Créneau $heure peu utilisé. Envisager une redistribution.";
            }
        }
        
        return $recommandations;
    }

    /**
     * Propose une réorganisation automatique
     */
    public function proposerReorganisation($departementId, $dateDebut, $dateFin)
    {
        $conflits = $this->detecterConflits(
            EmploiDuTemps::whereHas('annee', function($q) use ($departementId) {
                $q->where('departement_id', $departementId);
            })->whereBetween('date_debut', [$dateDebut, $dateFin])->get()
        );

        $propositions = [];
        
        foreach ($conflits as $conflit) {
            if ($conflit['type'] === 'formateur') {
                $propositions[] = $this->proposerSolutionConflitFormateur($conflit);
            } elseif ($conflit['type'] === 'annee') {
                $propositions[] = $this->proposerSolutionConflitAnnee($conflit);
            }
        }

        return [
            'conflits_detectes' => count($conflits),
            'propositions' => $propositions,
            'faisabilite' => $this->evaluerFaisabilite($propositions)
        ];
    }

    /**
     * Propose une solution pour un conflit de formateur
     */
    private function proposerSolutionConflitFormateur($conflit)
    {
        // Logique pour proposer des alternatives :
        // 1. Changer de créneau horaire
        // 2. Changer de formateur
        // 3. Regrouper les cours
        
        return [
            'conflit_id' => $conflit['emploi1_id'] . '_' . $conflit['emploi2_id'],
            'type_solution' => 'changement_creneau',
            'description' => 'Déplacer un des cours vers un créneau libre',
            'faisabilite' => 'haute'
        ];
    }

    /**
     * Propose une solution pour un conflit d'année
     */
    private function proposerSolutionConflitAnnee($conflit)
    {
        return [
            'conflit_id' => $conflit['emploi1_id'] . '_' . $conflit['emploi2_id'],
            'type_solution' => 'separation_cours',
            'description' => 'Séparer les cours sur des créneaux différents',
            'faisabilite' => 'moyenne'
        ];
    }

    /**
     * Évalue la faisabilité globale des propositions
     */
    private function evaluerFaisabilite($propositions)
    {
        if (empty($propositions)) {
            return 'aucune_action_requise';
        }
        
        $faisabilites = array_column($propositions, 'faisabilite');
        $hauteCount = count(array_filter($faisabilites, fn($f) => $f === 'haute'));
        $totalCount = count($faisabilites);
        
        if ($hauteCount / $totalCount > 0.8) {
            return 'tres_faisable';
        } elseif ($hauteCount / $totalCount > 0.5) {
            return 'moderement_faisable';
        } else {
            return 'peu_faisable';
        }
    }
}