<?php

namespace App\Console\Commands;

use App\Models\CanonicalFramework;
use App\Services\Assurance\FrameworkGapReport;
use Illuminate\Console\Command;

class ReportFrameworkGaps extends Command
{
    protected $signature = 'opengrc:evidence:gaps
                            {framework? : Framework code for a detailed report}
                            {--json : Print JSON instead of tables}';

    protected $description = 'Report outcomes with no control, no evidence, stale evidence, or a failing validation';

    public function handle(FrameworkGapReport $gaps): int
    {
        $framework = $this->argument('framework');

        if (is_string($framework) && $framework !== '') {
            return $this->detailed($gaps, $framework);
        }

        return $this->summary($gaps);
    }

    private function detailed(FrameworkGapReport $gaps, string $framework): int
    {
        if (! CanonicalFramework::query()->where('code', $framework)->exists()) {
            $this->error("Unknown framework [{$framework}]. Run with no argument to list frameworks.");

            return self::FAILURE;
        }

        $report = $gaps->forFramework($framework);

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info("Gap report: {$framework} ({$report['outcomes']} outcome(s))");

        $labels = [
            'unreviewed' => 'No reviewed control',
            'no_evidence' => 'No evidence',
            'stale_evidence' => 'Stale evidence',
            'failing_validation' => 'Failing validation',
        ];

        foreach ($labels as $key => $label) {
            $this->line('');
            $this->line("<fg=gray>{$label}</> (".count($report[$key]).')');

            if ($report[$key] === []) {
                $this->line('  none');

                continue;
            }

            foreach ($report[$key] as $entry) {
                $this->line(sprintf(
                    '  %s  %s  (controls %d, evidence %d, validations %d)',
                    $entry['code'],
                    $entry['name'],
                    $entry['controls'],
                    $entry['evidence'],
                    $entry['validations'],
                ));
            }
        }

        return self::SUCCESS;
    }

    private function summary(FrameworkGapReport $gaps): int
    {
        $frameworks = CanonicalFramework::query()->orderBy('priority')->orderBy('name')->get();

        if ($frameworks->isEmpty()) {
            $this->warn('No frameworks in the catalogue. Run opengrc:catalog:sync first.');

            return self::SUCCESS;
        }

        $rows = [];
        $json = [];

        foreach ($frameworks as $framework) {
            $report = $gaps->forFramework($framework->code);

            $rows[] = [
                $framework->code,
                $report['outcomes'],
                count($report['unreviewed']),
                count($report['no_evidence']),
                count($report['stale_evidence']),
                count($report['failing_validation']),
            ];

            $json[$framework->code] = [
                'outcomes' => $report['outcomes'],
                'unreviewed' => count($report['unreviewed']),
                'no_evidence' => count($report['no_evidence']),
                'stale_evidence' => count($report['stale_evidence']),
                'failing_validation' => count($report['failing_validation']),
            ];
        }

        if ($this->option('json')) {
            $this->line(json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(
            ['Framework', 'Outcomes', 'No control', 'No evidence', 'Stale', 'Failing'],
            $rows
        );

        $this->line('Pass a framework code for detail: php artisan opengrc:evidence:gaps cis_v8');

        return self::SUCCESS;
    }
}
