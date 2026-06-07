<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string $roles = null): Response
    {
        if (! $request->user()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        if (! $roles) {
            return $next($request);
        }

        $allowed = explode(',', $roles);
        if (! in_array($request->user()->role, $allowed, true)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
