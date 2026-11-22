<?php

namespace App\Services\Dlp;

use App\Models\DlpFinding;
use App\Models\DlpSuppression;
use Illuminate\Support\Collection;

/**
 * Which in-force suppression accepts a finding right now (ADR 0053).
 *
 * The query is loaded once per request (the binding is scoped) because the
 * finding table asks per row. A suppression is matched by policy, then by the
 * optional identifier type and match label; a suppression with no scope beyond
 * the policy accepts everything that policy raises.
 */
class DlpSuppressionResolver
{
    /** @var Collection<int, DlpSuppression>|null */
    protected ?Collection $inForce = null;

    /**
     * @return Collection<int, DlpSuppression>
     */
    public function inForce(): Collection
    {
        return $this->inForce ??= DlpSuppression::query()
            ->inForce()
            ->orderByDesc('id')
            ->get();
    }

    public function suppressionFor(DlpFinding $finding): ?DlpSuppression
    {
        return $this->inForce()->first(
            fn (DlpSuppression $suppression): bool => $suppression->appliesTo($finding)
        );
    }

    /**
     * Drop the cached set. Used after a suppression changes in the same request.
     */
    public function forget(): void
    {
        $this->inForce = null;
    }
}
