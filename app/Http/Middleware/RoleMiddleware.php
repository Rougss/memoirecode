<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string|array  ...$roles  // Liste des rôles autorisés (ex: 'Administrateur', 'Formateur')
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            // Pas d'utilisateur connecté
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        // Vérifie que l'utilisateur a un rôle
        if (!$user->role) {
            return response()->json(['message' => 'Aucun rôle attribué.'], 403);
        }

        // Vérifie si le rôle de l'utilisateur est dans la liste des rôles autorisés
        if (!in_array($user->role->intitule, $roles)) {
            return response()->json(['message' => "Accès refusé. Rôle requis : " . implode(', ', $roles)], 403);
        }

        return $next($request);
    }
}
