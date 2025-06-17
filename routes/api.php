<?php
// routes/api.php

use App\Http\Controllers\UtilisateurController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SpecialiteController;
use App\Http\Controllers\SalleController;
use App\Http\Controllers\BatimentController;
use App\Http\Controllers\EmploiDuTempsController;
use App\Http\Controllers\AnneeController;
use App\Http\Controllers\CompetenceController;
use App\Http\Controllers\MetierController;
use App\Http\Controllers\NiveauController;
use App\Http\Controllers\DepartementController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\TypeFormationController;
use App\Http\Controllers\SemestreController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\CompeSemestreController;
use App\Http\Controllers\CompEmploiController;
use App\Http\Controllers\FormaDepartController;
use App\Models\Salle;
use Illuminate\Support\Facades\Route;
use Mockery\Matcher\Type;

// ================================
// AUTHENTIFICATION (Non protégé)
// ================================
Route::post('/login', [AuthController::class, 'login']);

Route::get('/roles', [RoleController::class, 'index']);

// ================================
// ROUTES PROTÉGÉES PAR AUTH SEULEMENT
// ================================
Route::middleware(['auth:api'])->group(function () {
     Route::prefix('admin/emploi-du-temps')->group(function () {
        
        // 👈 VOS ROUTES EXISTANTES (à garder)
        Route::get('/', [EmploiDuTempsController::class, 'index']);
        Route::post('/', [EmploiDuTempsController::class, 'store']);
        Route::get('/formateur/{formateurId}', [EmploiDuTempsController::class, 'getFormateurSchedule']);
        Route::get('/annee/{anneeId}', [EmploiDuTempsController::class, 'getAnneeSchedule']);
        
        // 👈 NOUVELLES ROUTES À AJOUTER
        Route::post('/generer-auto', [EmploiDuTempsController::class, 'genererAutomatique']);
        Route::post('/analyser', [EmploiDuTempsController::class, 'analyserEmploi']);
        Route::post('/rapport', [EmploiDuTempsController::class, 'genererRapport']);
        Route::post('/reorganiser', [EmploiDuTempsController::class, 'proposerReorganisation']);
    });

    

    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    
    // Dashboard unifié
    Route::get('/dashboard', [UtilisateurController::class, 'dashboard']);
    
    // ================================
    // GESTION DU PROFIL
    // ================================
    Route::prefix('profile')->group(function () {
        Route::get('/', [UtilisateurController::class, 'profile']);
        Route::put('/{id}', [UtilisateurController::class, 'updateProfile']);
    });
    
    // ================================
    // ROUTES ADMINISTRATEUR (sans restriction de rôle)
    // ================================
    Route::prefix('admin')->group(function () {

        Route::get('/stats', [UtilisateurController::class, 'getStats']);
        
        // Gestion des utilisateurs
        Route::prefix('users')->group(function () {
            Route::get('/', [UtilisateurController::class, 'index']);
            Route::post('/ajouter', [UtilisateurController::class, 'store']);
            Route::get('/{id}', [UtilisateurController::class, 'show']);
            Route::put('/{id}', [UtilisateurController::class, 'update']);
            Route::delete('/{id}', [UtilisateurController::class, 'destroy']);
            Route::post('/{id}/reset-password', [UtilisateurController::class, 'resetPassword']);
             Route::get('/{id}/formateur', [UtilisateurController::class, 'getFormateurByUserId']);
        });

        Route::prefix('compesemestres')->group(function () {
            Route::get('/semestre/{semestreId}/competences', [CompeSemestreController::class, 'getCompetencesBySemestre']);
            Route::get('/competence/{competenceId}/semestres', [CompeSemestreController::class, 'getSemestresByCompetence']);
            Route::post('/ajouter', [CompeSemestreController::class, 'store']);
        });

        Route::prefix('compemplois')->group(function () {
            Route::get('/emploi/{emploi_du_tempsId}/competences', [CompEmploiController::class, 'getCompetencesByEmploi']);
            Route::get('/competence/{competenceId}/emploi_du_temps', [CompEmploiController::class, 'getEmploiDuTempsByCompetence']);
            Route::post('/ajouter', [CompEmploiController::class, 'store']);
        });

        Route::prefix('formadeparts')->group(function () {
            Route::get('/departement/{departementId}/formateurs', [FormaDepartController::class, 'getFormateursByDepartement']);
            Route::get('/formation/{formationId}/departements', [FormaDepartController::class, 'getDepartementsByFormation']);
            Route::post('/ajouter', [FormaDepartController::class, 'store']);
        });

         Route::put('/departements/{id}/chef', [DepartementController::class, 'assignerChef']);

        



       

        
        // Gestion des spécialités
        Route::apiResource('specialites', SpecialiteController::class);
        
        // Gestion des salles
        Route::apiResource('salles', SalleController::class);
       
        
        // Gestion des bâtiments
        Route::apiResource('batiments', BatimentController::class);
        
        // Gestion des emplois du temps
        Route::apiResource('emploi-du-temps', EmploiDuTempsController::class);
        
        // Gestion des années
        Route::apiResource('annees', AnneeController::class);
        
        // Gestion des compétences
        Route::apiResource('competences', CompetenceController::class);
        
        // Gestion des métiers
        Route::apiResource('metiers', MetierController::class);
        
        // Gestion des niveaux
        Route::apiResource('niveaux', NiveauController::class);
        
        // Gestion des départements
        Route::apiResource('departements', DepartementController::class);
        
        // Gestion des intégrations
        Route::apiResource('integrations', IntegrationController::class);
        
        // Gestion des types de formation
        Route::apiResource('types-formation', TypeFormationController::class);
        
        // Gestion des semestres
        Route::apiResource('semestres', SemestreController::class);

        Route::apiResource('roles', RoleController::class);
        
        // Statistiques générales
        Route::get('/stats', [UtilisateurController::class, 'getStats']);

        // Gestion des associations compétence-semestre
        Route::apiResource('compesemestres', CompeSemestreController::class);
    });
    
       

    // ================================
    // ROUTES DIRECTEUR DES ÉTUDES (sans restriction de rôle)
    // ================================
    Route::prefix('directeur')->group(function () {
        
        // Consultation des utilisateurs
        Route::get('/users', [UtilisateurController::class, 'index']);
        Route::get('/users/{id}', [UtilisateurController::class, 'show']);
        
        // Gestion pédagogique
        Route::get('/emplois-du-temps', [EmploiDuTempsController::class, 'index']);
        Route::post('/emplois-du-temps', [EmploiDuTempsController::class, 'store']);
        Route::put('/emplois-du-temps/{id}', [EmploiDuTempsController::class, 'update']);
        
        Route::get('/specialites', [SpecialiteController::class, 'index']);
        Route::get('/competences', [CompetenceController::class, 'index']);
        Route::get('/metiers', [MetierController::class, 'index']);
        
        // Affectations
        Route::post('/affecter-formateur', [UtilisateurController::class, 'affecterFormateur']);
        Route::post('/affecter-salle', [SalleController::class, 'affecter']);
        
        // Gestion des salles
        Route::get('/salles', [SalleController::class, 'index']);
        Route::get('/salles/disponibles', [SalleController::class, 'getSallesDisponibles']);
        Route::get('/salles/batiment/{batimentId}', [SalleController::class, 'getSallesByBatiment']);
    });
    
    // ================================
    // ROUTES FORMATEUR (sans restriction de rôle)
    // ================================
    Route::prefix('formateur')->group(function () {
        
        // Mes cours et emplois du temps
        Route::get('/mes-cours', [EmploiDuTempsController::class, 'mesCours']);
        Route::get('/mes-eleves', [UtilisateurController::class, 'mesEleves']);
        
        // Consultation des salles
        Route::get('/salles', [SalleController::class, 'index']);
        Route::get('/salles/disponibles', [SalleController::class, 'getSallesDisponibles']);
    });
    
    // ================================
    // ROUTES SURVEILLANT (sans restriction de rôle)
    // ================================
    Route::prefix('surveillant')->group(function () {
        
        // Consultation des élèves
        Route::get('/eleves', [UtilisateurController::class, 'getEleves']);
        Route::get('/eleves/{id}', [UtilisateurController::class, 'show']);
        
        // Emplois du temps
        Route::get('/emplois-du-temps', [EmploiDuTempsController::class, 'index']);
        
        // Surveillance
        Route::post('/signaler-incident', [UtilisateurController::class, 'signalerIncident']);
        Route::post('/marquer-presence', [UtilisateurController::class, 'marquerPresence']);
        
        // Gestion des salles
        Route::get('/salles', [SalleController::class, 'index']);
        Route::get('/salles/occupees', [SalleController::class, 'getSallesOccupees']);
    });
    
    // ================================
    // ROUTES ÉLÈVE (sans restriction de rôle)
    // ================================
    Route::prefix('eleve')->group(function () {
        
        // Mon emploi du temps
        Route::get('/mon-emploi-du-temps', [EmploiDuTempsController::class, 'monEmploi']);
        
        // Consultation des salles
        Route::get('/salles', [SalleController::class, 'index']);
    });
    
    // ================================
    // ROUTES COMMUNES
    // ================================
    Route::prefix('common')->group(function () {
        
        // Consultation des données de référence
        Route::get('/specialites', [SpecialiteController::class, 'index']);
        Route::get('/salles', [SalleController::class, 'index']);
        Route::get('/salles/disponibles', [SalleController::class, 'getSallesDisponibles']);
        Route::get('/metiers', [MetierController::class, 'index']);
        Route::get('/niveaux', [NiveauController::class, 'index']);
        Route::get('/departements', [DepartementController::class, 'index']);
        Route::get('/annees', [AnneeController::class, 'index']);
        Route::get('/semestres', [SemestreController::class, 'index']);
        Route::get('/types-formation', [TypeFormationController::class, 'index']);
        
        // Recherche globale
        Route::get('/search', [UtilisateurController::class, 'search']);
    });
});