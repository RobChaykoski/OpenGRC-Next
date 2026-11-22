<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate the access decision endpoint on a scoped, client-bound API token
 * (ADR 0051).
 *
 * Session authentication is deliberately rejected. A decision is requested by an
 * enforcement point — an EHR gateway, an API gateway, a policy agent — not by a
 * browser, and the caller must be identifiable so a decision can be attributed
 * and replayed safely.
 */
class EnsureAccessDecisionScope
{
    public const ABILITY = 'access.decide';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if (! $user || ! $token) {
            return response()->json(['message' => __('access_governance.decision.unauthenticated')], 401);
        }

        if (! $token instanceof PersonalAccessToken) {
            return response()->json([
                'message' => __('access_governance.decision.session_not_allowed'),
            ], 403);
        }

        if (! $token->can(self::ABILITY)) {
            return response()->json([
                'message' => __('access_governance.decision.missing_scope'),
            ], 403);
        }

        if (! is_string($token->client_id) || $token->client_id === '') {
            return response()->json([
                'message' => __('access_governance.decision.no_client'),
            ], 403);
        }

        return $next($request);
    }
}
