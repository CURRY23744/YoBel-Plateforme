<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Compte désactivé.', 'code' => 'account_disabled'], 403);
        }

        // Mot de passe temporaire : seules les routes d'authentification restent accessibles.
        if ($user->must_change_password && ! $request->is('api/auth/*')) {
            return response()->json([
                'message' => 'Vous devez changer votre mot de passe avant de continuer.',
                'code' => 'password_change_required',
            ], 403);
        }

        return $next($request);
    }
}
