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
        $motDePasse = $this->genererMotDePasse();
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
            'date_naissance' => $validated['date_naissance']?? null,
            'matricule' => $matricule,
            'genre' => $request->input('genre', 'M'), // Valeur par défaut 'M' si non spécifié
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
                    'matricule' => $user->matricule
                ]
            ], 201);
        }

        return redirect()->route('users.index')
                        ->with('success', 'Utilisateur créé avec succès. Mot de passe: ' . $motDePasse);
    }

    public function show(Request $request, $id)
    {
        $user = User::with('role')->findOrFail($id);
      //  $this->authorize('view', $user);
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $user
            ]);
        }
        
        return view('admin.users.show', compact('user'));
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
        $user = User::findOrFail($id);
       // $this->authorize('update', $user);
        
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'telephone' => 'nullable|string',
            'date_naissance' => 'nullable|date',
            'matricule' => 'nullable|string|unique:users,matricule,' . $id,
            'genre' => 'nullable|in:M,F',
            'lieu_naissance' => 'nullable|string',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);
    
        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('photos', 'public');
        }
    
        $user->update($validated);
    
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Utilisateur mis à jour avec succès',
                'data' => $user->fresh()->load('role')
            ]);
        }
    
        return redirect()->route('users.index')
                        ->with('success', 'Utilisateur mis à jour avec succès');
    }
    public function destroy(Request $request, $id)
    {
        $user = User::findOrFail($id);
       // $this->authorize('delete', $user);
        
        // Supprimer l'enregistrement spécifique selon le rôle
        $this->supprimerUtilisateurSpecifique($user);
        
        $user->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Utilisateur supprimé avec succès'
            ]);
        }

        return redirect()->route('users.index')
                        ->with('success', 'Utilisateur supprimé avec succès');
    }

    // GESTION DU PROFIL (Tous les utilisateurs peuvent modifier leur profil)
    
    public function profile()
    {
        $user = Auth::user();
        return view('profile.edit', compact('user'));
    }

    public function updateProfile(Request $request)
    {
       /** @var \App\Models\User $user */ 
        $user = Auth::user();
          
            
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'telephone' => 'nullable|string',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'password' => 'nullable|string|min:8|confirmed',
        ]);
    
        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('photos', 'public');
        }
    
        if ($request->filled('password')) {
            $validated['password'] = Hash::make($request->password);
        } else {
            unset($validated['password']);
        }
    
     
        $user->update($validated);
    
    
    
        return redirect()->route('profile')
                        ->with('success', 'Profil mis à jour avec succès');
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
    
    private function genererMotDePasse()
    {
        return 'temp' . rand(1000, 9999);
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
}
