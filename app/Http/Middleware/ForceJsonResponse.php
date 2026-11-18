<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API clients always want JSON, including for authentication failures.
 *
 * Without this, a missing token redirects to the login page (302) instead of
 * returning 401, which discovery dispatchers correctly treat as a failure but
 * cannot diagnose.
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
