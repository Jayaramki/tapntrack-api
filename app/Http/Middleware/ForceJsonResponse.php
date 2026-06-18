<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * This backend serves a JSON API only. Force every request to be treated as a
 * JSON request so validation/auth failures render as JSON (422/401) instead of
 * redirecting (302) when a client omits the Accept header.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
