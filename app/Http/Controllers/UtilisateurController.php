<?php

namespace App\Http\Controllers;

use App\Models\User ;
use App\Models\Role;
use App\Models\Eleve;
use App\Models\Formateur;
use App\Models\Surveillant;
use App\Models\Administrateur;
use App\Models\DirecteurDesEtude;
use App\Models\Chef_Departement;
use App\Models\Specialite;
use App\Models\Departement;
use App\Models\Salle;
use App\Models\Batiment;
use App\Models\Niveau;
use App\Models\Annee;
use App\Models\TypeFormation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Controllers\Log;


class UtilisateurController extends Controller
{
    use AuthorizesRequests;
    // Dashboard spécifique selon le rôle de l'utilisateur connecté
    public function dashboard()
    {
        $user = Auth::user(); // Ensure $user is an instance of the User model
        if (!$user instanceof \App\Models\User) {
            abort(500, 'Authenticated user is not a valid User instance.');
        }
        
        // Redirection selon le rôle
        switch($user->role->intitule) {
            case 'Administrateur':
                return $this->adminDashboard();
            case 'Directeur des Etudes':
                return $this->directeurDashboard();
            case 'Formateur':
                return $this->formateurDashboard();
            case 'Surveillant':
                return $this->surveillantDashboard();
            case 'Elève':
                return $this->eleveDashboard();
            case 'Chef_Departement':
                return $this->chef_DepartementDashboard();
            default:
                return redirect()->route('login');
        }
    }

    public function getStats()
{
    try {
        // Compter les différentes entités
        $stats = [
            'utilisateurs' => User::count(),
            'specialites' => Specialite::count(),
            'departements' => Departement::count(),
            'salles' => Salle::count(),
            'batiments' => Batiment::count(),
            'niveaux' => Niveau::count(),
            'annees' => Annee::count(),
            'formations' => TypeFormation::count(),
        ];

        // Statistiques détaillées par rôle (optionnel)
        $statsDetaillees = [
            'utilisateurs_par_role' => User::selectRaw('role, COUNT(*) as count')
                ->groupBy('role')
                ->get()
                ->pluck('count', 'role')
                ->toArray(),
            
            'utilisateurs_actifs' => User::where('statut', 'actif')->count(),
            'utilisateurs_inactifs' => User::where('statut', 'inactif')->count(),
        ];

        return response()->json(array_merge($stats, $statsDetaillees), 200);

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Erreur lors de la récupération des statistiques',
            'message' => $e->getMessage()
        ], 500);
    }
}

public function getFormateurByUserId($userId)
{
    $formateur = Formateur::where('user_id', $userId)->with('user')->first();
    
    if (!$formateur) {
        return response()->json([
            'success' => false,
            'message' => 'Formateur non trouvé'
        ], 404);
    }

    return response()->json([
        'success' => true,
        'data' => $formateur
    ]);
}

    // Dashboard administrateur (statistiques)
    private function adminDashboard()
    {
        $totalUsers = User::count();
        $totalEleves = Eleve::count();
        $totalFormateurs = Formateur::count();
        $totalSurveilants = Surveillant::count();
        $totalChefsDepartement = Chef_Departement::count();
        
        return view('dashboard.admin', compact('totalUsers', 'totalEleves', 'totalFormateurs', 'totalSurveilants', 'totalChefsDepartement'));
    }

    // Dashboards spécifiques pour chaque rôle
    private function directeurDashboard()
    {
        return view('dashboard.directeur');
    }

    private function formateurDashboard()
    {
        return view('dashboard.formateur');
    }

    private function surveillantDashboard()
    {
        return view('dashboard.surveillant');
    }

    private function eleveDashboard()
    {
        return view('dashboard.eleve');
    }
    private function chef_DepartementDashboard()
    {
        return view('dashboard.chef_depart');
    }

    // GESTION DES UTILISATEURS (Réservée aux Administrateurs)
    
    public function index(Request $request)
    {
        // Vérifier que l'utilisateur est administrateur
       // $this->authorize('viewAny', User::class);
        
        $users = User::with('role')->paginate(10);
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $users
            ]);
        }
        
        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        //$this->authorize('create', User::class);
        
        $roles = Role::all();
        return view('admin.users.create', compact('roles'));
    }

   public function store(Request $request)
{
    // $this->authorize('create', User::class);

    // Validation
    $role = Role::find($request->role_id);
    
    // Validation de base
    $rules = [
        'nom' => 'required|string|max:255',
        'prenom' => 'required|string|max:255',
        'email' => 'required|email|unique:users',
        'telephone' => 'nullable|string|max:20',
        'date_naissance' => 'nullable|date',
        'matricule' => 'nullable|string|unique:users|max:50',
        'genre' => 'nullable|in:M,F',
        'lieu_naissance' => 'nullable|string|max:255',
        'role_id' => 'required|exists:roles,id',
        'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        'contact_urgence' => 'nullable|string|max:255',
        'metier_id' => 'nullable|exists:metiers,id',
    ];

    // Ajouter des règles spécifiques selon le rôle
    if ($role && $role->intitule === 'Formateur') {
        $rules['specialite_id'] = 'required|exists:specialites,id';
    } else {
        $rules['specialite_id'] = 'nullable|exists:specialites,id';
    }

    if ($role && $role->intitule === 'Elève') {
        $rules['metier_id'] = 'required|exists:metiers,id';
    }

    $validated = $request->validate($rules);
    
    // Génération automatique du mot de passe et matricule
    $motDePasse = $this->genererMotDePasse($validated['role_id']);
    $matricule = $validated['matricule'] ?? $this->genererMatricule();
    
    // Gestion de l'upload de photo
    $photoPath = null;
    if ($request->hasFile('photo')) {
        $photoPath = $request->file('photo')->store('photos', 'public');
    }

    // Création de l'utilisateur
    $user = User::create([
        'nom' => $validated['nom'],
        'prenom' => $validated['prenom'],
        'email' => $validated['email'],
        'telephone' => $validated['telephone'],
        'date_naissance' => $validated['date_naissance'] ?? null,
        'matricule' => $matricule,
        'genre' => $request->input('genre', 'M'),
        'lieu_naissance' => $validated['lieu_naissance'],
        'password' => Hash::make($motDePasse),
        'photo' => $photoPath,
        'role_id' => $validated['role_id']
    ]);

    // Créer l'enregistrement spécifique selon le rôle
    $this->creerUtilisateurSpecifique($user, $validated['role_id'], $request);

    if ($request->expectsJson()) {
        return response()->json([
            'success' => true,
            'message' => 'Utilisateur créé avec succès',
            'data' => [
                'user' => $user->load('role'),
                'password' => $motDePasse,
                'matricule' => $user->matricule,
                'role' => $role->intitule
            ]
        ], 201);
    }

    return redirect()->route('users.index')
                    ->with('success', 'Utilisateur créé avec succès. Mot de passe: ' . $motDePasse);
}

   public function show(Request $request, $id)
{
    try {
        $user = User::with(['role'])->findOrFail($id);
        
        // Ajouter le nom du rôle pour la compatibilité
        if ($user->role) {
            $user->role_name = $user->role->intitule;
        }
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $user
            ], 200);
        }
        
        return view('admin.users.show', compact('user'));
        
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }
        
        return redirect()->route('users.index')
                        ->with('error', 'Utilisateur non trouvé');
    } catch (\Exception $e) {
        \Log::error('Erreur lors de la récupération de l\'utilisateur', [
            'user_id' => $id,
            'error' => $e->getMessage()
        ]);
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération de l\'utilisateur'
            ], 500);
        }
        
        return redirect()->route('users.index')
                        ->with('error', 'Erreur lors de la récupération de l\'utilisateur');
    }
}

    public function edit($id)
    {
        $user = User::query()->findOrFail($id);
       // $this->authorize('update', $user);
        
        $roles = Role::all();
        return view('admin.users.edit', compact('user', 'roles'));
    }

   public function update(Request $request, $id)
{
    try {
        $user = User::findOrFail($id);
        
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'telephone' => 'nullable|string|max:20',
            'date_naissance' => 'nullable|date',
            'matricule' => 'nullable|string|unique:users,matricule,' . $id,
            'genre' => 'nullable|in:M,F',
            'lieu_naissance' => 'nullable|string|max:255',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ], [
            'nom.required' => 'Le nom est requis',
            'prenom.required' => 'Le prénom est requis',
            'email.required' => 'L\'email est requis',
            'email.email' => 'L\'email doit être valide',
            'email.unique' => 'Cet email est déjà utilisé',
            'telephone.max' => 'Le téléphone ne peut pas dépasser 20 caractères',
            'date_naissance.date' => 'Format de date invalide',
            'matricule.unique' => 'Ce matricule est déjà utilisé',
            'genre.in' => 'Le genre doit être M ou F',
            'lieu_naissance.max' => 'Le lieu de naissance ne peut pas dépasser 255 caractères',
            'photo.image' => 'Le fichier doit être une image',
            'photo.mimes' => 'L\'image doit être au format jpeg, png ou jpg',
            'photo.max' => 'L\'image ne peut pas dépasser 2MB'
        ]);

        // Gestion de l'upload de photo
        if ($request->hasFile('photo')) {
            // Supprimer l'ancienne photo si elle existe
            if ($user->photo) {
                \Storage::disk('public')->delete($user->photo);
            }
            $validated['photo'] = $request->file('photo')->store('photos', 'public');
        }

        // Mettre à jour l'utilisateur
        $user->update($validated);

        // Recharger l'utilisateur avec ses relations
        $user = $user->fresh()->load('role');
        
        // Ajouter le nom du rôle pour la compatibilité
        if ($user->role) {
            $user->role_name = $user->role->intitule;
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Utilisateur mis à jour avec succès',
                'data' => $user
            ], 200);
        }

        return redirect()->route('users.index')
                        ->with('success', 'Utilisateur mis à jour avec succès');
                        
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }
        
        return redirect()->route('users.index')
                        ->with('error', 'Utilisateur non trouvé');
                        
    } catch (\Illuminate\Validation\ValidationException $e) {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        }
        
        return redirect()->back()
                        ->withErrors($e->errors())
                        ->withInput();
                        
    } catch (\Exception $e) {
        \Log::error('Erreur lors de la mise à jour de l\'utilisateur', [
            'user_id' => $id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne s\'est produite'
            ], 500);
        }

        return redirect()->back()
                        ->with('error', 'Une erreur s\'est produite lors de la mise à jour')
                        ->withInput();
    }
}
   public function destroy(Request $request, $id)
{
    try {
        $user = User::findOrFail($id);
        
        // Vérifier que l'utilisateur n'essaie pas de se supprimer lui-même
        if (Auth::id() == $user->id) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas supprimer votre propre compte'
                ], 403);
            }
            
            return redirect()->route('users.index')
                            ->with('error', 'Vous ne pouvez pas supprimer votre propre compte');
        }
        
        // Supprimer la photo si elle existe
        if ($user->photo) {
            \Storage::disk('public')->delete($user->photo);
        }
        
        // Supprimer l'enregistrement spécifique selon le rôle
        $this->supprimerUtilisateurSpecifique($user);
        
        // Supprimer l'utilisateur
        $user->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Utilisateur supprimé avec succès'
            ], 200);
        }

        return redirect()->route('users.index')
                        ->with('success', 'Utilisateur supprimé avec succès');
                        
    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non trouvé'
            ], 404);
        }
        
        return redirect()->route('users.index')
                        ->with('error', 'Utilisateur non trouvé');
                        
    } catch (\Exception $e) {
        \Log::error('Erreur lors de la suppression de l\'utilisateur', [
            'user_id' => $id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne s\'est produite'
            ], 500);
        }

        return redirect()->route('users.index')
                        ->with('error', 'Une erreur s\'est produite lors de la suppression');
    }
}

    // GESTION DU PROFIL (Tous les utilisateurs peuvent modifier leur profil)
    
    public function profile()
    {
        $user = Auth::user();
        return view('profile.edit', compact('user'));
    }

   public function updateProfile(Request $request)
{
    try {
        /** @var \App\Models\User $user */ 
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié'
            ], 401);
        }
            
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'telephone' => 'nullable|string|max:20',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ], [
            'nom.required' => 'Le nom est requis',
            'prenom.required' => 'Le prénom est requis',
            'email.required' => 'L\'email est requis',
            'email.email' => 'L\'email doit être valide',
            'email.unique' => 'Cet email est déjà utilisé',
            'telephone.max' => 'Le téléphone ne peut pas dépasser 20 caractères',
            'photo.image' => 'Le fichier doit être une image',
            'photo.mimes' => 'L\'image doit être au format jpeg, png ou jpg',
            'photo.max' => 'L\'image ne peut pas dépasser 2MB'
        ]);

        // Gestion de l'upload de photo
        if ($request->hasFile('photo')) {
            // Supprimer l'ancienne photo si elle existe
            if ($user->photo) {
                \Storage::disk('public')->delete($user->photo);
            }
            $validated['photo'] = $request->file('photo')->store('photos', 'public');
        }

        // Mettre à jour les informations
        $user->update($validated);

        // Recharger l'utilisateur avec ses relations
        $user = $user->fresh()->load('role');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Profil mis à jour avec succès',
                'data' => $user
            ], 200);
        }

        return redirect()->route('profile')
                        ->with('success', 'Profil mis à jour avec succès');

    } catch (\Illuminate\Validation\ValidationException $e) {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $e->errors()
            ], 422);
        }
        
        return redirect()->back()
                        ->withErrors($e->errors())
                        ->withInput();
    } catch (\Exception $e) {
        \Log::error('Erreur lors de la mise à jour du profil', [
            'user_id' => Auth::id(),
            'error' => $e->getMessage()
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne s\'est produite'
            ], 500);
        }

        return redirect()->back()
                        ->with('error', 'Une erreur s\'est produite lors de la mise à jour');
    }
}
    // Réinitialiser le mot de passe (Admin seulement)
    public function resetPassword(Request $request, $id)
    {
        $user = User::findOrFail($id);
       // $this->authorize('update', $user);
        
        $nouveauMotDePasse = $this->genererMotDePasse();
        
        $user->update([
            'password' => Hash::make($nouveauMotDePasse)
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Mot de passe réinitialisé avec succès',
                'data' => [
                    'new_password' => $nouveauMotDePasse
                ]
            ]);
        }

        return redirect()->back()
                        ->with('success', 'Mot de passe réinitialisé: ' . $nouveauMotDePasse);
    }

    // MÉTHODES PRIVÉES
    
    private function genererMotDePasse($roleId)
    {
        $role = Role::find($roleId);
        
        switch($role->intitule) {
            case 'Formateur':
                return 'formateur2025'; 
            case 'Elève':
                return 'eleve2025'; 
            case 'Surveillant':
                return 'surveillant2025'; 
            case 'Directeur des Etudes':
                return 'directeur2025';
            case 'Administrateur':
                return 'admin2025'; 
            default:
                return 'utilisateur2025';   
        }
    }

    private function genererMatricule()
    {
        return 'MAT' . date('Y') . rand(1000, 9999);
    }

    private function creerUtilisateurSpecifique($user, $roleId, $request)
    {
        $role = Role::find($roleId);
        
        switch($role->intitule) {
            case 'Elève':
                Eleve::create([
                    'user_id' => $user->id,
                    'contact_urgence' => $request->contact_urgence,
                    'metier_id' => $request->metier_id,
                    
                ]);
                break;
                
            case 'Formateur':
                Formateur::create([
                    'user_id' => $user->id,
                    'specialite_id' => $request->specialite_id,
                ]);
                break;
                
            case 'Surveillant':
                Surveillant::create([
                    'user_id' => $user->id
                ]);
                break;
                
            case 'Administrateur':
                Administrateur::create([
                    'user_id' => $user->id
                ]);
                break;
                
            case 'Directeur des Etudes':
                DirecteurDesEtude::create([
                    'user_id' => $user->id
                ]);
                break;
        }
    }

    private function supprimerUtilisateurSpecifique($user)
    {
        $role = $user->role;
        
        switch($role->intitule) {
            case 'Elève':
                Eleve::where('user_id', $user->id)->delete();
                break;
            case 'Formateur':
                Formateur::where('user_id', $user->id)->delete();
                break;
            case 'Surveillant':
                Surveillant::where('user_id', $user->id)->delete();
                break;
            case 'Administrateur':
                Administrateur::where('user_id', $user->id)->delete();
                break;
            case 'Directeur des Etudes':
                DirecteurDesEtude::where('user_id', $user->id)->delete();
                break;
        
        }
    }

    public function getEleveByUserId($userId)
{
    try {
        $eleve = Eleve::where('user_id', $userId)
                    ->with(['user', 'metier']) // Charger les relations
                    ->first();
        
        if (!$eleve) {
            return response()->json([
                'success' => false,
                'message' => 'Élève non trouvé'
            ], 404);
        }

        // Préparer les données de réponse
        $eleveData = [
            'id' => $eleve->id,
            'contact_urgence' => $eleve->contact_urgence,
            'user_id' => $eleve->user_id,
            'metier_id' => $eleve->metier_id,
            'user' => $eleve->user ? [
                'id' => $eleve->user->id,
                'nom' => $eleve->user->nom,
                'prenom' => $eleve->user->prenom,
                'email' => $eleve->user->email,
            ] : null,
            'metier' => $eleve->metier ? [
                'id' => $eleve->metier->id,
                'intitule' => $eleve->metier->intitule,
                'description' => $eleve->metier->description ?? null,
            ] : null,
        ];

        return response()->json([
            'success' => true,
            'data' => $eleveData
        ], 200);

    } catch (\Exception $e) {
        \Log::error('Erreur lors de la récupération des données élève', [
            'user_id' => $userId,
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la récupération des données élève'
        ], 500);
    }
}
    public function changePassword(Request $request)
{
    try {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Utilisateur non authentifié'
            ], 401);
        }

        // Validation des données
        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:6|confirmed',
            'new_password_confirmation' => 'required|string'
        ], [
            'current_password.required' => 'L\'ancien mot de passe est requis',
            'new_password.required' => 'Le nouveau mot de passe est requis',
            'new_password.min' => 'Le nouveau mot de passe doit contenir au moins 6 caractères',
            'new_password.confirmed' => 'La confirmation du mot de passe ne correspond pas',
            'new_password_confirmation.required' => 'La confirmation du mot de passe est requise'
        ]);

        // Vérifier l'ancien mot de passe
        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'L\'ancien mot de passe est incorrect',
                'errors' => [
                    'current_password' => ['L\'ancien mot de passe est incorrect']
                ]
            ], 422);
        }

        // Vérifier que le nouveau mot de passe est différent de l'ancien
        if (Hash::check($validated['new_password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Le nouveau mot de passe doit être différent de l\'ancien',
                'errors' => [
                    'new_password' => ['Le nouveau mot de passe doit être différent de l\'ancien']
                ]
            ], 422);
        }

        // Mettre à jour le mot de passe
        $user->update([
            'password' => Hash::make($validated['new_password'])
        ]);

        // Log de l'action (optionnel)
        \Log::info('Mot de passe changé', [
            'user_id' => $user->id,
            'email' => $user->email,
            'timestamp' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Mot de passe modifié avec succès'
        ], 200);

    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur de validation',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        \Log::error('Erreur lors du changement de mot de passe', [
            'user_id' => Auth::id(),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Une erreur interne s\'est produite'
        ], 500);
    }
}
}
