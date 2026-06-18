<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        // Laravel splits `role:a,b` into separate params, so accept them variadically.
        if (empty($roles)) {
            return $next($request);
        }

        if (! in_array($request->user()->role, $roles, true)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
