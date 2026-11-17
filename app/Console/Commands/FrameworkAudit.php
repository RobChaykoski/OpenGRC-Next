<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class FrameworkAudit extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'opengrc:framework-audit
                            {--json : Output machine-readable JSON}
                            {--strict : Treat warnings as failures}
                            {--seeder= : Only audit standards produced by this seeder}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate imported frameworks against the provenance register: counts, duplicate identifiers, and empty content';

    /**
     * Collected issues keyed by severity.
     *
     * @var array<int, array{severity: string, framework: string, message: string}>
     */
    protected array $issues = [];

    /**
     * Collected per-framework results.
     *
     * @var array<int, array<string, mixed>>
     */
    protected array $results = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $catalog = config('framework_catalog.standards', []);
        $seederFilter = $this->option('seeder');

        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $this->record('fail', 'database', 'cannot connect: '.$e->getMessage());
            $this->finish();

            return Command::FAILURE;
        }

        foreach ($catalog as $code => $definition) {
            if ($seederFilter && ($definition['seeder'] ?? null) !== $seederFilter) {
                continue;
            }

            $this->auditFramework($code, $definition);
        }

        $this->auditOrphans();

        $failures = $this->countSeverity('fail');
        $warnings = $this->countSeverity('warn');
        $ok = $failures === 0 && (! $this->option('strict') || $warnings === 0);

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'ok' => $ok,
                'summary' => [
                    'frameworks' => count($this->results),
                    'fail' => $failures,
                    'warn' => $warnings,
                ],
                'frameworks' => $this->results,
                'issues' => $this->issues,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->renderTable();
            $this->renderIssues();
        }

        return $ok ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Audit one framework entry from the register.
     *
     * @param  array<string, mixed>  $definition
     */
    protected function auditFramework(string $code, array $definition): void
    {
        $name = $definition['name'] ?? $code;
        $data = base_path($definition['data'] ?? '');

        if (($definition['data'] ?? null) && ! is_file($data)) {
            $this->record('fail', $code, 'source data file is missing: '.($definition['data'] ?? ''));
        }

        $standard = DB::table('standards')->where('code', $code)->first();

        if (! $standard) {
            $this->results[] = [
                'code' => $code,
                'name' => $name,
                'status' => 'not imported',
                'controls' => null,
                'expected' => $definition['expected_controls'] ?? null,
                'licence' => $definition['licence'] ?? null,
            ];

            return;
        }

        $controls = DB::table('controls')->where('standard_id', $standard->id);
        $count = (clone $controls)->count();
        $expected = $definition['expected_controls'] ?? null;

        $status = 'ok';

        if ($count === 0) {
            $this->record('fail', $code, 'standard is imported but has no controls');
            $status = 'error';
        } elseif ($expected !== null && $count !== $expected) {
            $this->record('warn', $code, "control count {$count} does not match expected {$expected}");
            $status = 'count mismatch';
        }

        $duplicates = (clone $controls)
            ->select('code', DB::raw('count(*) as occurrences'))
            ->whereNotNull('code')
            ->groupBy('code')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            $this->record('fail', $code, "duplicate control identifier [{$duplicate->code}] x{$duplicate->occurrences}");
            $status = 'error';
        }

        $missingTitles = (clone $controls)->where(fn ($query) => $query->whereNull('title')->orWhere('title', ''))->count();
        if ($missingTitles > 0) {
            $this->record('fail', $code, "{$missingTitles} control(s) have no title");
            $status = 'error';
        }

        $missingDescriptions = (clone $controls)
            ->where(fn ($query) => $query->whereNull('description')->orWhere('description', ''))
            ->count();

        if ($missingDescriptions > 0) {
            $this->record('warn', $code, "{$missingDescriptions} control(s) have no description text");
            $status = $status === 'ok' ? 'incomplete' : $status;
        }

        if (($definition['redistributable'] ?? 'yes') === 'review') {
            $this->record('warn', $code, 'licence requires review before redistribution');
        }

        $this->results[] = [
            'code' => $code,
            'name' => $name,
            'status' => $status,
            'controls' => $count,
            'expected' => $expected,
            'licence' => $definition['licence'] ?? null,
            'redistributable' => $definition['redistributable'] ?? null,
        ];
    }

    /**
     * Controls that belong to no standard cannot be reported on.
     */
    protected function auditOrphans(): void
    {
        $orphans = DB::table('controls')
            ->leftJoin('standards', 'controls.standard_id', '=', 'standards.id')
            ->whereNull('standards.id')
            ->count();

        if ($orphans > 0) {
            $this->record('fail', 'controls', "{$orphans} control(s) are not attached to a standard");
        }
    }

    /**
     * Record an issue.
     */
    protected function record(string $severity, string $framework, string $message): void
    {
        $this->issues[] = ['severity' => $severity, 'framework' => $framework, 'message' => $message];
    }

    /**
     * Count issues by severity.
     */
    protected function countSeverity(string $severity): int
    {
        return count(array_filter($this->issues, fn (array $issue) => $issue['severity'] === $severity));
    }

    /**
     * Render one row per framework.
     */
    protected function renderTable(): void
    {
        $this->newLine();
        $this->line('<fg=cyan>Framework audit</>');
        $this->newLine();

        $this->table(
            ['Framework', 'Code', 'Status', 'Controls', 'Expected', 'Redistributable'],
            array_map(fn (array $result) => [
                $result['name'],
                $result['code'],
                $result['status'],
                $result['controls'] ?? '-',
                $result['expected'] ?? '-',
                $result['redistributable'] ?? '-',
            ], $this->results)
        );
    }

    /**
     * Render issues and a summary.
     */
    protected function renderIssues(): void
    {
        if ($this->issues !== []) {
            $this->table(
                ['Severity', 'Framework', 'Issue'],
                array_map(fn (array $issue) => [$issue['severity'], $issue['framework'], $issue['message']], $this->issues)
            );
        }

        $failures = $this->countSeverity('fail');
        $warnings = $this->countSeverity('warn');

        $this->line(sprintf('Frameworks: %d   Warn: %d   Fail: %d', count($this->results), $warnings, $failures));

        if ($failures > 0) {
            $this->error('Framework audit found failures.');
        } elseif ($warnings > 0) {
            $this->warn('Framework audit found warnings.');
        } else {
            $this->info('Framework data is consistent with the provenance register.');
        }

        $this->newLine();
    }

    /**
     * Nothing further: output already rendered.
     */
    protected function finish(): void
    {
        if ($this->option('json')) {
            $this->line((string) json_encode([
                'ok' => false,
                'summary' => ['frameworks' => 0, 'fail' => $this->countSeverity('fail'), 'warn' => 0],
                'frameworks' => [],
                'issues' => $this->issues,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }

        $this->error('Framework audit could not run.');
    }
}
