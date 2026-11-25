<?php

namespace App\Services\Integrity;

use App\Enums\IntegrityCheckStatus;
use App\Models\IntegrityCheck;
use App\Services\Audit\AuditLedger;
use App\Services\Evidence\EvidenceLedger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

/**
 * Verify the append-only chains and keep the result.
 *
 * The two chains answer different questions. The evidence ledger recomputes the
 * hash of every stored file and each subject's entry chain, so a replaced file or
 * an edited row is visible. The audit ledger recomputes the activity chain, so a
 * reordered or edited event is visible. A manual `doctor` run checks both; this
 * service is what the scheduler calls, and it writes a row whether it passes or
 * fails so the dashboard can tell "verified" from "nobody looked".
 *
 * Verification never repairs anything. A failure is a decision for a person.
 */
class IntegrityVerifier
{
    public const EVIDENCE = 'evidence';

    public const AUDIT = 'audit';

    /** @var array<int, string> */
    public const CHECKS = [self::EVIDENCE, self::AUDIT];

    /**
     * Cap the per-run detail so one broken installation cannot write a huge
     * JSON blob. The counts are always complete.
     */
    private const DETAIL_LIMIT = 50;

    public function __construct(
        protected EvidenceLedger $evidence,
        protected AuditLedger $audit,
    ) {}

    /**
     * @return array<int, IntegrityCheck>
     */
    public function run(?string $only = null): array
    {
        $checks = $only === null ? self::CHECKS : [$only];

        if (array_diff($checks, self::CHECKS) !== []) {
            throw new InvalidArgumentException("Unknown integrity check [{$only}].");
        }

        $runs = [];

        foreach ($checks as $check) {
            $run = $this->runOne($check);
            $runs[] = $run;

            if ($run->failed()) {
                Log::critical('Integrity verification failed.', [
                    'check' => $run->check,
                    'summary' => $run->summary,
                ]);
            } elseif ($run->warned()) {
                Log::warning('Integrity verification reported a warning.', [
                    'check' => $run->check,
                    'summary' => $run->summary,
                ]);
            }
        }

        $this->prune();

        return $runs;
    }

    public function runOne(string $check): IntegrityCheck
    {
        $started = microtime(true);

        $result = match ($check) {
            self::EVIDENCE => $this->verifyEvidence(),
            self::AUDIT => $this->verifyAudit(),
            default => throw new InvalidArgumentException("Unknown integrity check [{$check}]."),
        };

        return IntegrityCheck::create([
            'check' => $check,
            'status' => $result['status'],
            'summary' => $result['summary'],
            'detail' => $result['detail'],
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'checked_at' => now(),
        ]);
    }

    /**
     * @return array{status: IntegrityCheckStatus, summary: string, detail: array<string, mixed>}
     */
    protected function verifyEvidence(): array
    {
        if (! Schema::hasTable('evidence_ledger_entries') || ! Schema::hasTable('evidence_states')) {
            return [
                'status' => IntegrityCheckStatus::Warn,
                'summary' => __('integrity.summary.not_installed', [
                    'check' => __('integrity.check.evidence'),
                ]),
                'detail' => ['installed' => false],
            ];
        }

        try {
            $report = $this->evidence->verifyAll();
        } catch (Throwable $e) {
            Log::error('Integrity verification could not read the evidence ledger.', [
                'exception' => $e::class,
            ]);

            return [
                'status' => IntegrityCheckStatus::Fail,
                'summary' => __('integrity.summary.unreadable', [
                    'check' => __('integrity.check.evidence'),
                ]),
                'detail' => ['exception' => $e::class],
            ];
        }

        $errors = $report['errors'];
        $warnings = $report['warnings'];

        $status = $errors !== []
            ? IntegrityCheckStatus::Fail
            : ($warnings !== [] ? IntegrityCheckStatus::Warn : IntegrityCheckStatus::Pass);

        $summary = match ($status) {
            IntegrityCheckStatus::Fail => __('integrity.summary.evidence_failed', [
                'count' => $report['checked'],
                'problems' => count($errors),
            ]),
            IntegrityCheckStatus::Warn => __('integrity.summary.evidence_warned', [
                'count' => $report['checked'],
                'warnings' => count($warnings),
            ]),
            IntegrityCheckStatus::Pass => __('integrity.summary.evidence_passed', [
                'count' => $report['checked'],
            ]),
        };

        return [
            'status' => $status,
            'summary' => $summary,
            'detail' => [
                'checked' => $report['checked'],
                'error_count' => count($errors),
                'warning_count' => count($warnings),
                'errors' => array_slice($errors, 0, self::DETAIL_LIMIT),
                'warnings' => array_slice($warnings, 0, self::DETAIL_LIMIT),
                'truncated' => count($errors) > self::DETAIL_LIMIT || count($warnings) > self::DETAIL_LIMIT,
            ],
        ];
    }

    /**
     * @return array{status: IntegrityCheckStatus, summary: string, detail: array<string, mixed>}
     */
    protected function verifyAudit(): array
    {
        if (! Schema::hasTable('audit_ledger_entries')) {
            return [
                'status' => IntegrityCheckStatus::Warn,
                'summary' => __('integrity.summary.not_installed', [
                    'check' => __('integrity.check.audit'),
                ]),
                'detail' => ['installed' => false],
            ];
        }

        try {
            $report = $this->audit->verify();
        } catch (Throwable $e) {
            Log::error('Integrity verification could not read the audit ledger.', [
                'exception' => $e::class,
            ]);

            return [
                'status' => IntegrityCheckStatus::Fail,
                'summary' => __('integrity.summary.unreadable', [
                    'check' => __('integrity.check.audit'),
                ]),
                'detail' => ['exception' => $e::class],
            ];
        }

        if ($report['broken'] !== null) {
            return [
                'status' => IntegrityCheckStatus::Fail,
                'summary' => __('integrity.summary.audit_broken', ['entry' => $report['broken']]),
                'detail' => $report,
            ];
        }

        if ($report['altered'] > 0) {
            return [
                'status' => IntegrityCheckStatus::Fail,
                'summary' => __('integrity.summary.audit_altered', ['count' => $report['altered']]),
                'detail' => $report,
            ];
        }

        $summary = __('integrity.summary.audit_passed', ['total' => $report['total']]);

        if ($report['missing'] > 0) {
            $summary .= ' '.__('integrity.summary.audit_pruned', ['missing' => $report['missing']]);
        }

        return [
            'status' => IntegrityCheckStatus::Pass,
            'summary' => $summary,
            'detail' => $report,
        ];
    }

    /**
     * Old runs are operational history, not evidence. Delete them after the
     * retention window; the chains they verified are untouched.
     */
    protected function prune(): void
    {
        $days = (int) config('integrity.retention_days', 400);

        if ($days <= 0) {
            return;
        }

        IntegrityCheck::query()
            ->where('checked_at', '<', now()->subDays($days))
            ->delete();
    }
}
