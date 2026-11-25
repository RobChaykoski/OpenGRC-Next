<?php

namespace App\Console\Commands;

use App\Enums\IntegrityCheckStatus;
use App\Models\IntegrityCheck;
use App\Services\Integrity\IntegrityVerifier;
use Illuminate\Console\Command;

/**
 * Run the integrity checks and persist the outcome.
 *
 * The existing `opengrc:evidence:verify` and `opengrc:audit:verify` commands are
 * the operator-facing detail. This command is the scheduled one: it runs the
 * same verifiers, stores a row per chain, and returns a non-zero exit code when
 * a chain is broken so cron and monitoring can act on it.
 */
class VerifyIntegrity extends Command
{
    protected $signature = 'opengrc:integrity:verify
                            {--check=all : evidence, audit, or all}
                            {--strict : Treat warnings as failures}
                            {--json : Output JSON}';

    protected $description = 'Verify the evidence and audit hash chains and record the result';

    public function handle(IntegrityVerifier $verifier): int
    {
        $check = (string) $this->option('check');

        if ($check !== 'all' && ! in_array($check, IntegrityVerifier::CHECKS, true)) {
            $this->error(__('integrity.command.unknown_check', ['check' => $check]));

            return self::INVALID;
        }

        $runs = $verifier->run($check === 'all' ? null : $check);

        if ($this->option('json')) {
            $this->line(json_encode(
                array_map(fn (IntegrityCheck $run): array => [
                    'check' => $run->check,
                    'status' => $run->status->value,
                    'summary' => $run->summary,
                    'duration_ms' => $run->duration_ms,
                    'checked_at' => $run->checked_at?->toIso8601String(),
                    'detail' => $run->detail,
                ], $runs),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ));
        } else {
            foreach ($runs as $run) {
                $line = sprintf(
                    '%s: %s - %s',
                    __('integrity.check.'.$run->check),
                    __('integrity.command.'.$run->status->value),
                    $run->summary
                );

                match ($run->status) {
                    IntegrityCheckStatus::Fail => $this->error($line),
                    IntegrityCheckStatus::Warn => $this->warn($line),
                    IntegrityCheckStatus::Pass => $this->info($line),
                };

                $this->line('  '.__('integrity.command.detail', [
                    'time' => $run->checked_at?->toDateTimeString(),
                    'duration' => $run->duration_ms,
                ]));
            }
        }

        $failed = collect($runs)->contains(fn (IntegrityCheck $run): bool => $run->failed())
            || ($this->option('strict') && collect($runs)->contains(fn (IntegrityCheck $run): bool => $run->warned()));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
