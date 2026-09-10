<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Réserve l'accès aux fonctions d'administration (Réglages, utilisateurs)
 * aux comptes ayant le rôle « admin ». Les secrétaires en sont exclues.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user() && $request->user()->isAdmin(), 403, 'Accès réservé aux administrateurs.');

        return $next($request);
    }
}
