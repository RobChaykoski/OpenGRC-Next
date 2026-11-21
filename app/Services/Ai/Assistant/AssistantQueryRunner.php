<?php

namespace App\Services\Ai\Assistant;

use App\Enums\AssistantIntent;
use App\Enums\PolicyExceptionStatus;
use App\Models\Audit;
use App\Models\CanonicalControl;
use App\Models\CanonicalFramework;
use App\Models\ControlAttestation;
use App\Models\Finding;
use App\Models\Implementation;
use App\Models\NonHumanIdentity;
use App\Models\Policy;
use App\Models\PolicyAcknowledgement;
use App\Models\PolicyException;
use App\Models\Risk;
use App\Models\User;
use App\Services\Assurance\FrameworkGapReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

/**
 * The read-only query catalogue behind the assistant.
 *
 * Each method is a reviewed question with a fixed query, a bounded result and
 * no payload text. A new question is a code change and a test, not a prompt.
 * Permission checks use the same policies as the screens, so a future
 * restriction cannot be bypassed by asking the assistant instead.
 */
final class AssistantQueryRunner
{
    private const LIMIT = 25;

    public function __construct(
        private readonly FrameworkGapReport $gaps,
    ) {}

    public function run(AssistantIntent $intent, ?CanonicalFramework $framework): AssistantQueryResult
    {
        return match ($intent) {
            AssistantIntent::FrameworkGaps => $this->frameworkGaps($framework),
            AssistantIntent::OverdueTests => $this->overdueTests(),
            AssistantIntent::OpenFindings => $this->openFindings(),
            AssistantIntent::RiskAppetite => $this->riskAppetite(),
            AssistantIntent::ExpiringExceptions => $this->expiringExceptions(),
            AssistantIntent::PolicyAcknowledgements => $this->policyAcknowledgements(),
            AssistantIntent::ControlAttestations => $this->controlAttestations(),
            AssistantIntent::NhiRotation => $this->nhiRotation(),
            AssistantIntent::AuditProgress => $this->auditProgress(),
        };
    }

    private function frameworkGaps(?CanonicalFramework $framework): AssistantQueryResult
    {
        if ($framework === null) {
            return AssistantQueryResult::needsFramework();
        }

        if (! $this->allowed(CanonicalControl::class)) {
            return AssistantQueryResult::denied();
        }

        $report = $this->gaps->forFramework((string) $framework->code);
        $rows = $this->gaps->condensed((string) $framework->code);

        $counts = [
            $this->count('outcomes', $report['outcomes']),
            $this->count('unreviewed', count($report['unreviewed'])),
            $this->count('no_evidence', count($report['no_evidence'])),
            $this->count('stale', count($report['stale_evidence'])),
            $this->count('failing', count($report['failing_validation'])),
        ];

        $mapped = array_map(fn (array $row): array => [
            'code' => $row['code'],
            'name' => $row['name'],
            'reasons' => implode(', ', array_map(
                fn (string $reason): string => __('assistant.reasons.'.$reason),
                $row['reasons']
            )),
        ], $rows);

        return new AssistantQueryResult(
            permitted: true,
            rows: array_slice($mapped, 0, self::LIMIT),
            columns: [
                'code' => __('assistant.columns.code'),
                'name' => __('assistant.columns.name'),
                'reasons' => __('assistant.columns.reasons'),
            ],
            counts: $counts,
            context: "Framework {$framework->code} ({$framework->name}), ".count($rows).' outcomes with at least one gap reason.',
            notice: count($mapped) > self::LIMIT ? $this->truncated(count($mapped)) : null,
        );
    }

    private function overdueTests(): AssistantQueryResult
    {
        if (! $this->allowed(Implementation::class)) {
            return AssistantQueryResult::denied();
        }

        $horizon = today()->addDays(30);

        $implementations = Implementation::query()
            ->whereNotNull('next_test_at')
            ->whereDate('next_test_at', '<=', $horizon)
            ->orderBy('next_test_at')
            ->get(['code', 'title', 'next_test_at']);

        $overdue = $implementations->filter(fn (Implementation $item): bool => $item->next_test_at?->lt(today()) ?? false);

        $rows = $implementations->take(self::LIMIT)->map(fn (Implementation $item): array => [
            'code' => $item->code,
            'title' => $item->title,
            'next_test_at' => $item->next_test_at?->toDateString() ?? '—',
            'days_overdue' => $item->next_test_at?->lt(today())
                ? (int) $item->next_test_at->startOfDay()->diffInDays(today())
                : 0,
        ])->all();

        return new AssistantQueryResult(
            permitted: true,
            rows: $rows,
            columns: [
                'code' => __('assistant.columns.code'),
                'title' => __('assistant.columns.title'),
                'next_test_at' => __('assistant.columns.next_test'),
                'days_overdue' => __('assistant.columns.days_overdue'),
            ],
            counts: [
                $this->count('overdue', $overdue->count()),
                $this->count('due_soon', $implementations->count() - $overdue->count()),
            ],
            context: $implementations->count().' implementations have a test due within 30 days; '.$overdue->count().' are overdue.',
            notice: $implementations->count() > self::LIMIT ? $this->truncated($implementations->count()) : null,
        );
    }

    private function openFindings(): AssistantQueryResult
    {
        if (! $this->allowed(Finding::class)) {
            return AssistantQueryResult::denied();
        }

        $findings = Finding::query()
            ->whereIn('status', ['open', 'in_progress'])
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->get(['id', 'title', 'severity', 'status', 'due_at']);

        $open = $findings->filter(fn (Finding $finding): bool => $finding->status?->value === 'open')->count();
        $withoutDue = $findings->filter(fn (Finding $finding): bool => $finding->due_at === null)->count();
        $overdue = $findings->filter(fn (Finding $finding): bool => $finding->due_at?->lt(today()) ?? false)->count();

        $rows = $findings->take(self::LIMIT)->map(fn (Finding $finding): array => [
            'reference' => $finding->reference(),
            'title' => $finding->title,
            'severity' => $finding->severity?->getLabel() ?? '—',
            'status' => $finding->status?->getLabel() ?? '—',
            'due_at' => $finding->due_at?->toDateString() ?? '—',
        ])->all();

        return new AssistantQueryResult(
            permitted: true,
            rows: $rows,
            columns: [
                'reference' => __('assistant.columns.reference'),
                'title' => __('assistant.columns.title'),
                'severity' => __('assistant.columns.severity'),
                'status' => __('assistant.columns.status'),
                'due_at' => __('assistant.columns.due'),
            ],
            counts: [
                $this->count('open_findings', $open),
                $this->count('in_progress', $findings->count() - $open),
                $this->count('no_due_date', $withoutDue),
                $this->count('overdue', $overdue),
            ],
            context: $findings->count().' open or in-progress findings; '.$overdue.' past their due date.',
            notice: $findings->count() > self::LIMIT ? $this->truncated($findings->count()) : null,
        );
    }

    private function riskAppetite(): AssistantQueryResult
    {
        if (! $this->allowed(Risk::class)) {
            return AssistantQueryResult::denied();
        }

        $threshold = max(1, min(25, (int) setting('risk.appetite_threshold', 13)));

        $risks = Risk::query()
            ->where('is_active', true)
            ->get(['code', 'name', 'residual_likelihood', 'residual_impact', 'acceptance_status', 'acceptance_expires_at']);

        $above = $risks->filter(
            fn (Risk $risk): bool => ((int) $risk->residual_likelihood) * ((int) $risk->residual_impact) >= $threshold
        );

        $accepted = $above->filter(fn (Risk $risk): bool => (bool) $risk->isAcceptanceActive())->count();

        $rows = $above->take(self::LIMIT)->map(fn (Risk $risk): array => [
            'code' => $risk->code,
            'name' => $risk->name,
            'residual' => (int) $risk->residual_likelihood * (int) $risk->residual_impact,
            'inherent' => (int) $risk->inherent_likelihood * (int) $risk->inherent_impact,
            'acceptance' => $risk->isAcceptanceActive()
                ? __('assistant.values.accepted_until', ['date' => $risk->acceptance_expires_at?->toDateString()])
                : __('assistant.values.not_accepted'),
        ])->all();

        return new AssistantQueryResult(
            permitted: true,
            rows: $rows,
            columns: [
                'code' => __('assistant.columns.code'),
                'name' => __('assistant.columns.name'),
                'residual' => __('assistant.columns.residual'),
                'inherent' => __('assistant.columns.inherent'),
                'acceptance' => __('assistant.columns.acceptance'),
            ],
            counts: [
                $this->count('active_risks', $risks->count()),
                $this->count('above_appetite', $above->count()),
                $this->count('accepted', $accepted),
                $this->count('unaccepted', $above->count() - $accepted),
            ],
            context: "The global appetite threshold is {$threshold}. ".$above->count().' active risks are at or above it.',
            notice: $above->count() > self::LIMIT ? $this->truncated($above->count()) : null,
        );
    }

    private function expiringExceptions(): AssistantQueryResult
    {
        if (! $this->allowed(PolicyException::class)) {
            return AssistantQueryResult::denied();
        }

        $exceptions = PolicyException::query()
            ->with('policy:id,code,name')
            ->where('status', PolicyExceptionStatus::Approved->value)
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '<=', today()->addDays(30))
            ->orderBy('expiration_date')
            ->get();

        $expired = $exceptions->filter(fn (PolicyException $exception): bool => $exception->expiration_date?->lt(today()) ?? false);

        $rows = $exceptions->take(self::LIMIT)->map(fn (PolicyException $exception): array => [
            'policy' => trim(($exception->policy?->code ?? '').' '.($exception->policy?->name ?? '')) ?: '—',
            'exception' => $exception->name,
            'expiration_date' => $exception->expiration_date?->toDateString() ?? '—',
            'days_left' => $exception->expiration_date?->lt(today())
                ? -1 * (int) $exception->expiration_date->startOfDay()->diffInDays(today())
                : (int) today()->diffInDays($exception->expiration_date),
        ])->all();

        return new AssistantQueryResult(
            permitted: true,
            rows: $rows,
            columns: [
                'policy' => __('assistant.columns.policy'),
                'exception' => __('assistant.columns.exception'),
                'expiration_date' => __('assistant.columns.expiration'),
                'days_left' => __('assistant.columns.days_left'),
            ],
            counts: [
                $this->count('expired', $expired->count()),
                $this->count('expiring', $exceptions->count() - $expired->count()),
            ],
            context: $exceptions->count().' approved exceptions expire within 30 days or have already expired.',
            notice: $exceptions->count() > self::LIMIT ? $this->truncated($exceptions->count()) : null,
        );
    }

    private function policyAcknowledgements(): AssistantQueryResult
    {
        if (! $this->allowed(Policy::class)) {
            return AssistantQueryResult::denied();
        }

        $policies = Policy::query()
            ->whereNull('retired_date')
            ->orderBy('code')
            ->get();

        $users = User::query()->count();
        $fullyAcknowledged = 0;

        $rows = $policies->map(function (Policy $policy) use ($users, &$fullyAcknowledged): array {
            $acknowledged = PolicyAcknowledgement::query()
                ->where('policy_id', $policy->getKey())
                ->where('policy_revision', $policy->currentRevision())
                ->count();

            $missing = max(0, $users - $acknowledged);

            if ($missing === 0 && $users > 0) {
                $fullyAcknowledged++;
            }

            return [
                'code' => $policy->code,
                'name' => $policy->name,
                'acknowledged' => $acknowledged,
                'users' => $users,
                'missing' => $missing,
                'coverage' => $users > 0 ? (int) round($acknowledged / $users * 100).'%' : '—',
            ];
        })->all();

        return new AssistantQueryResult(
            permitted: true,
            rows: array_slice($rows, 0, self::LIMIT),
            columns: [
                'code' => __('assistant.columns.code'),
                'name' => __('assistant.columns.title'),
                'acknowledged' => __('assistant.columns.acknowledged'),
                'users' => __('assistant.columns.users'),
                'coverage' => __('assistant.columns.coverage'),
            ],
            counts: [
                $this->count('policies', $policies->count()),
                $this->count('users', $users),
                $this->count('fully_acknowledged', $fullyAcknowledged),
                $this->count('below_full', max(0, $policies->count() - $fullyAcknowledged)),
            ],
            context: $policies->count()." active policies for {$users} users; {$fullyAcknowledged} have current acknowledgement from every user.",
            notice: count($rows) > self::LIMIT ? $this->truncated(count($rows)) : null,
        );
    }

    private function controlAttestations(): AssistantQueryResult
    {
        if (! $this->allowed(ControlAttestation::class)) {
            return AssistantQueryResult::denied();
        }

        $attestations = ControlAttestation::query()
            ->with('implementation:id,code,title')
            ->where('status', 'pending')
            ->whereDate('due_at', '<=', today()->addDays(14))
            ->orderBy('due_at')
            ->get();

        $overdue = $attestations->filter(fn (ControlAttestation $attestation): bool => $attestation->due_at?->lt(today()) ?? false);

        $rows = $attestations->take(self::LIMIT)->map(fn (ControlAttestation $attestation): array => [
            'reference' => $attestation->reference(),
            'implementation' => trim(($attestation->implementation?->code ?? '').' '.($attestation->implementation?->title ?? '')) ?: '—',
            'due_at' => $attestation->due_at?->toDateString() ?? '—',
            'overdue' => $attestation->due_at?->lt(today()) ? __('assistant.values.yes') : __('assistant.values.no'),
        ])->all();

        return new AssistantQueryResult(
            permitted: true,
            rows: $rows,
            columns: [
                'reference' => __('assistant.columns.reference'),
                'implementation' => __('assistant.columns.implementation'),
                'due_at' => __('assistant.columns.due'),
                'overdue' => __('assistant.columns.overdue'),
            ],
            counts: [
                $this->count('pending', $attestations->count()),
                $this->count('overdue', $overdue->count()),
            ],
            context: $attestations->count().' control attestations are pending with a due date in the next 14 days; '.$overdue->count().' are overdue.',
            notice: $attestations->count() > self::LIMIT ? $this->truncated($attestations->count()) : null,
        );
    }

    private function nhiRotation(): AssistantQueryResult
    {
        if (! $this->allowed(NonHumanIdentity::class)) {
            return AssistantQueryResult::denied();
        }

        $identities = NonHumanIdentity::query()
            ->whereNotIn('status', ['revoked'])
            ->where(function (Builder $query): void {
                $query->whereDate('next_rotation_at', '<=', today()->addDays(30))
                    ->orWhereDate('expires_at', '<=', today()->addDays(30));
            })
            ->orderBy('next_rotation_at')
            ->get();

        $rotationOverdue = $identities->filter(fn (NonHumanIdentity $identity): bool => $identity->next_rotation_at?->lt(today()) ?? false);
        $expiring = $identities->filter(fn (NonHumanIdentity $identity): bool => $identity->expires_at?->lt(today()->addDays(30)) ?? false);

        $rows = $identities->take(self::LIMIT)->map(fn (NonHumanIdentity $identity): array => [
            'name' => $identity->name,
            'type' => $identity->type?->getLabel() ?? '—',
            'system' => $identity->system,
            'rotation_due' => $identity->next_rotation_at?->toDateString() ?? '—',
            'expires' => $identity->expires_at?->toDateString() ?? '—',
            'status' => $identity->status?->getLabel() ?? '—',
        ])->all();

        return new AssistantQueryResult(
            permitted: true,
            rows: $rows,
            columns: [
                'name' => __('assistant.columns.name'),
                'type' => __('assistant.columns.type'),
                'system' => __('assistant.columns.system'),
                'rotation_due' => __('assistant.columns.rotation_due'),
                'expires' => __('assistant.columns.expires'),
                'status' => __('assistant.columns.status'),
            ],
            counts: [
                $this->count('rotation_due', $rotationOverdue->count()),
                $this->count('expiring_soon', $expiring->count()),
            ],
            context: $identities->count().' non-human identities need rotation or expire within 30 days; '.$rotationOverdue->count().' rotations are overdue.',
            notice: $identities->count() > self::LIMIT ? $this->truncated($identities->count()) : null,
        );
    }

    private function auditProgress(): AssistantQueryResult
    {
        if (! $this->allowed(Audit::class)) {
            return AssistantQueryResult::denied();
        }

        $audits = Audit::query()
            ->withCount([
                'auditItems as items_total',
                'auditItems as items_completed' => fn (Builder $query): Builder => $query->where('status', 'Completed'),
            ])
            ->where('status', 'In Progress')
            ->orderBy('end_date')
            ->get(['id', 'title', 'end_date', 'audit_type']);

        $pastEnd = $audits->filter(fn (Audit $audit): bool => $audit->end_date !== null && $audit->end_date < today()->toDateString());

        $rows = $audits->take(self::LIMIT)->map(function (Audit $audit): array {
            $total = (int) $audit->getAttribute('items_total');
            $completed = (int) $audit->getAttribute('items_completed');

            return [
                'title' => $audit->title,
                'type' => ucfirst((string) $audit->audit_type),
                'end_date' => $audit->end_date,
                'items' => $total,
                'completed' => $completed,
                'open' => max(0, $total - $completed),
            ];
        })->all();

        return new AssistantQueryResult(
            permitted: true,
            rows: $rows,
            columns: [
                'title' => __('assistant.columns.audit'),
                'type' => __('assistant.columns.type'),
                'end_date' => __('assistant.columns.end_date'),
                'items' => __('assistant.columns.items'),
                'completed' => __('assistant.columns.completed'),
                'open' => __('assistant.columns.open'),
            ],
            counts: [
                $this->count('in_progress_audits', $audits->count()),
                $this->count('past_end', $pastEnd->count()),
                $this->count('items_total', (int) $audits->sum(fn (Audit $audit): int => (int) $audit->getAttribute('items_total'))),
                $this->count('items_open', (int) $audits->sum(fn (Audit $audit): int => max(0, (int) $audit->getAttribute('items_total') - (int) $audit->getAttribute('items_completed')))),
            ],
            context: $audits->count().' audits are in progress; '.$pastEnd->count().' are past their planned end date.',
            notice: $audits->count() > self::LIMIT ? $this->truncated($audits->count()) : null,
        );
    }

    /**
     * @return array{label: string, value: int}
     */
    private function count(string $key, int $value): array
    {
        return [
            'label' => __('assistant.counts.'.$key),
            'value' => $value,
        ];
    }

    private function truncated(int $total): string
    {
        return __('assistant.notice.truncated', ['shown' => self::LIMIT, 'total' => $total]);
    }

    /**
     * @param  class-string  $model
     */
    private function allowed(string $model): bool
    {
        return Gate::allows('viewAny', $model);
    }
}
