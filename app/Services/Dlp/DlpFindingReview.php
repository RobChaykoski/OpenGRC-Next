<?php

namespace App\Services\Dlp;

use App\Enums\DlpDisposition;
use App\Models\DlpFinding;
use App\Models\User;

/**
 * Record a human review outcome for a DLP finding.
 *
 * Only the disposition and notes change. The detected labels, severity, source,
 * action and content hash stay as the detector recorded them.
 */
class DlpFindingReview
{
    public function record(
        DlpFinding $finding,
        DlpDisposition $disposition,
        ?string $notes = null,
        ?User $reviewer = null,
    ): DlpFinding {
        $finding->update([
            'disposition' => $disposition,
            'review_notes' => $notes,
            'reviewed_by' => $reviewer?->getKey() ?? auth()->id(),
            'reviewed_at' => now(),
        ]);

        return $finding->refresh();
    }
}
