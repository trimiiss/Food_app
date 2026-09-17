<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for /api/v1/admin/*. Runs after auth:sanctum, so an anonymous caller
 * gets 401 (who are you?) and a signed-in customer gets 403 (not allowed).
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'This action requires administrator privileges.');
        }

        return $next($request);
    }
}
