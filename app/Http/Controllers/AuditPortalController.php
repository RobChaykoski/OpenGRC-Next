<?php

namespace App\Http\Controllers;

use App\Models\AuditPortalGrant;
use App\Services\AppLogger;
use App\Services\Audit\AuditPortalService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public, read-only auditor portal (ADR 0045).
 *
 * Access is granted by an unguessable link, scoped to one audit. The controller
 * does not authenticate a user: the link is the capability, it is revocable,
 * it expires, and every successful read is recorded.
 */
class AuditPortalController extends Controller
{
    public function show(Request $request, string $token, AuditPortalService $portal): Response
    {
        $grant = AuditPortalGrant::findByToken($token);

        if ($grant === null) {
            abort(404);
        }

        if (! $grant->isActive()) {
            AppLogger::warning(
                category: 'audit_portal',
                event: 'LinkRejected',
                message: 'Inactive auditor portal link used',
                context: [
                    'audit_portal_grant_id' => $grant->getKey(),
                    'audit_id' => $grant->audit_id,
                    'state' => $grant->state(),
                ],
                subject: $grant,
            );

            return $this->noStore(response()->view('audit-portal.unavailable', [
                'organization' => setting('general.name', config('app.name')),
            ], 410));
        }

        $grant->recordView($request);

        AppLogger::info(
            category: 'audit_portal',
            event: 'PortalView',
            message: 'Auditor portal link viewed',
            context: [
                'audit_portal_grant_id' => $grant->getKey(),
                'audit_id' => $grant->audit_id,
                'access_count' => $grant->access_count,
            ],
            subject: $grant,
        );

        return $this->noStore(response()->view('audit-portal.show', array_merge(
            $portal->build($grant->audit),
            [
                'grant' => $grant,
                'organization' => setting('general.name', config('app.name')),
            ]
        )));
    }

    private function noStore(Response $response): Response
    {
        // The link itself is the secret; keep it out of referrers and caches, and
        // out of search indexes even if someone pastes it into a public place.
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $response->headers->set('Cache-Control', 'no-store, private, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
