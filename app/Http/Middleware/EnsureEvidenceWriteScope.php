<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate evidence ingestion on a scoped, client-bound API token.
 *
 * Session authentication is deliberately rejected: evidence is written by the
 * discovery service, not by a browser. A token must carry the evidence.write
 * ability and a client_id so the record can be scoped to a tenant.
 */
class EnsureEvidenceWriteScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (! $user || ! $token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! $token instanceof PersonalAccessToken) {
            return response()->json([
                'message' => 'This endpoint requires a scoped API token, not a browser session.',
            ], 403);
        }

        if (! $token->can('evidence.write')) {
            return response()->json([
                'message' => 'The token is missing the evidence.write scope.',
            ], 403);
        }

        if (! is_string($token->client_id) || $token->client_id === '') {
            return response()->json([
                'message' => 'The token is not bound to a client.',
            ], 403);
        }

        return $next($request);
    }
}
