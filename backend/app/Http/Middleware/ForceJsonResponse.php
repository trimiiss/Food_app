<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Treat every API request as if it sent `Accept: application/json`.
 *
 * Laravel decides between "redirect" and "JSON error" by inspecting the Accept
 * header. Without this, a client that omits the header (curl, a bare Postman
 * call) hitting a protected route is redirected to route('login') — which does
 * not exist in an API-only app and surfaces as a 500. Likewise validation
 * failures would redirect back instead of returning a 422 payload.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
