<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tant qu'un utilisateur a un mot de passe provisoire (must_change_password),
 * il est redirigé vers la page de changement de mot de passe.
 */
class ForceChangePassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password
            && ! $request->routeIs('admin.motdepasse')
            && ! $request->routeIs('logout')) {
            return redirect()->route('admin.motdepasse');
        }

        return $next($request);
    }
}
