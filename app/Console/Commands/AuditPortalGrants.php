<?php

namespace App\Console\Commands;

use App\Models\AuditPortalGrant;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Inspect and revoke read-only auditor portal links (ADR 0045).
 *
 * Revocation is deliberately available from the console as well as the UI: an
 * incident may need the link killed when the admin panel is the thing being
 * investigated.
 */
class AuditPortalGrants extends Command
{
    protected $signature = 'opengrc:audits:portal
                            {--all : Include expired and revoked links}
                            {--audit= : Only links for this audit ID}
                            {--revoke= : Revoke the link with this ID}';

    protected $description = 'List auditor portal links and revoke one by ID';

    public function handle(): int
    {
        if ($this->option('revoke') !== null) {
            return $this->revoke((int) $this->option('revoke'));
        }

        $query = AuditPortalGrant::query()
            ->with('audit:id,title')
            ->when(! $this->option('all'), fn (Builder $query) => $query->active())
            ->when($this->option('audit') !== null, fn (Builder $query) => $query->where('audit_id', (int) $this->option('audit')))
            ->orderBy('expires_at');

        $grants = $query->get();

        if ($grants->isEmpty()) {
            $this->info($this->option('all')
                ? 'No auditor portal links exist.'
                : 'No active auditor portal links. Use --all to include expired and revoked links.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Audit', 'Auditor', 'State', 'Expires', 'Opens', 'Last opened'],
            $grants->map(fn (AuditPortalGrant $grant): array => [
                (string) $grant->getKey(),
                $grant->audit?->title ?? '—',
                $grant->label,
                ucfirst($grant->state()),
                $grant->expires_at->format('Y-m-d H:i'),
                (string) $grant->access_count,
                $grant->last_accessed_at?->format('Y-m-d H:i') ?? '—',
            ])->all()
        );

        $active = $grants->filter(fn (AuditPortalGrant $grant): bool => $grant->isActive())->count();
        $this->line("{$grants->count()} link(s) listed; {$active} active.");

        return self::SUCCESS;
    }

    private function revoke(int $id): int
    {
        $grant = AuditPortalGrant::query()->find($id);

        if ($grant === null) {
            $this->error("No auditor portal link found for ID {$id}.");

            return self::FAILURE;
        }

        if ($grant->revoked_at !== null) {
            $this->warn("Link {$id} was already revoked at {$grant->revoked_at->toDateTimeString()}.");

            return self::SUCCESS;
        }

        $grant->revoke(null, 'Revoked from opengrc:audits:portal');

        $this->info("Revoked auditor portal link {$id} ({$grant->label}).");

        return self::SUCCESS;
    }
}
