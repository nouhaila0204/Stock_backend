<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Vérifie d'abord si l'utilisateur est authentifié via Sanctum
        if (!auth()->check()) {
            return response()->json([
                'message' => 'Vous devez être connecté pour accéder à cette ressource'
            ], 401);
        }

        // Ensuite vérifie le rôle admin
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Accès non autorisé - réservé aux administrateurs'
            ], 403);
        }

        return $next($request);
    }
}

