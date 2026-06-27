<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds defense-in-depth security headers to every API response and strips
 * framework/server fingerprints. The SPA's own CSP is set at the web server
 * (.htaccess); this covers the JSON API host.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $headers = [
            // Force HTTPS for a year incl. subdomains (safe: app runs HTTPS-only).
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), browsing-topics=()',
            // API returns JSON only — lock down what a response could ever load.
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'",
        ];

        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        // Remove fingerprints that aid targeted attacks.
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }
}
