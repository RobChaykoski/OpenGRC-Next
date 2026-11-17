<?php

namespace App\Console\Commands;

use App\Support\DatabasePath;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Passport;
use Throwable;

class Doctor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'opengrc:doctor
                            {--json : Output machine-readable JSON}
                            {--strict : Treat warnings as failures}
                            {--skip-queue : Skip queue and failed-job checks}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check runtime, configuration, database, and build health against the supported baseline';

    /**
     * Collected check results.
     *
     * @var array<int, array{name: string, status: string, detail: string}>
     */
    protected array $checks = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->checkRuntime();
        $this->checkEnvironment();
        $this->checkFilesystem();
        $this->checkBuild();
        $this->checkDatabase();
        $this->checkOauthKeys();
        $this->checkVersion();

        $failures = $this->countByStatus('fail');
        $warnings = $this->countByStatus('warn');

        $ok = $failures === 0 && (! $this->option('strict') || $warnings === 0);

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'ok' => $ok,
                'php' => PHP_VERSION,
                'environment' => app()->environment(),
                'summary' => [
                    'pass' => $this->countByStatus('pass'),
                    'warn' => $warnings,
                    'fail' => $failures,
                ],
                'checks' => $this->checks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->renderTable();
            $this->renderSummary($failures, $warnings);
        }

        return $ok ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * PHP version and required extensions.
     */
    protected function checkRuntime(): void
    {
        $minimum = '8.4.0';
        $this->record(
            'PHP version',
            version_compare(PHP_VERSION, $minimum, '>=') ? 'pass' : 'fail',
            PHP_VERSION." (minimum {$minimum})"
        );

        $required = ['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'hash', 'json', 'libxml',
            'mbstring', 'openssl', 'pcre', 'pdo', 'pdo_sqlite', 'phar', 'session',
            'simplexml', 'tokenizer', 'xml', 'xmlreader', 'xmlwriter', 'zip', ];

        $missing = array_values(array_filter($required, fn (string $ext) => ! extension_loaded($ext)));
        $this->record(
            'PHP extensions',
            $missing === [] ? 'pass' : 'fail',
            $missing === [] ? 'all required extensions loaded' : 'missing: '.implode(', ', $missing)
        );

        $recommended = ['bcmath', 'gd', 'intl', 'sodium'];
        $missingRecommended = array_values(array_filter($recommended, fn (string $ext) => ! extension_loaded($ext)));

        if ($missingRecommended !== []) {
            $this->record('Recommended extensions', 'warn', 'not loaded: '.implode(', ', $missingRecommended));
        }
    }

    /**
     * Configuration that commonly breaks a deploy.
     */
    protected function checkEnvironment(): void
    {
        $key = config('app.key');
        $this->record(
            'Application key',
            empty($key) ? 'fail' : 'pass',
            empty($key) ? 'APP_KEY is empty; run php artisan key:generate' : 'set'
        );

        $url = (string) config('app.url');
        $valid = $url !== '' && preg_match('#^https?://#i', $url) === 1;
        $placeholder = str_contains($url, 'opengrc.test') || str_contains($url, 'example.com');

        $this->record(
            'Application URL',
            ! $valid ? 'fail' : ($placeholder ? 'warn' : 'pass'),
            match (true) {
                $url === '' => 'APP_URL is empty; links in email and reports will break',
                ! $valid => "not an http(s) URL: {$url}",
                $placeholder => "{$url} is still the installer default",
                default => $url,
            }
        );

        if (app()->environment('production')) {
            $this->record(
                'Debug mode',
                config('app.debug') ? 'fail' : 'pass',
                config('app.debug') ? 'APP_DEBUG is enabled in production' : 'disabled in production'
            );
        }

        $connection = (string) config('database.default');
        $this->record('Database driver', 'pass', $connection);

        if (DatabasePath::hasWindowsSeparators(config('database.connections.'.$connection.'.database'))) {
            $this->record(
                'Database path',
                'warn',
                'contains backslashes; use forward slashes so Dotenv can parse it'
            );
        }
    }

    /**
     * Writable directories and the public storage link.
     */
    protected function checkFilesystem(): void
    {
        foreach (['storage' => storage_path(), 'bootstrap/cache' => base_path('bootstrap/cache')] as $label => $path) {
            $this->record(
                "Writable {$label}",
                is_dir($path) && is_writable($path) ? 'pass' : 'fail',
                $path
            );
        }

        $link = public_path('storage');
        $linked = file_exists($link) && realpath($link) !== false;
        $this->record(
            'Public storage link',
            $linked ? 'pass' : 'warn',
            $linked ? 'present' : 'missing; run php artisan storage:link'
        );
    }

    /**
     * Compiled front-end assets.
     */
    protected function checkBuild(): void
    {
        $manifest = public_path('build/manifest.json');
        $this->record(
            'Asset manifest',
            is_file($manifest) ? 'pass' : 'warn',
            is_file($manifest) ? 'present' : 'missing; run npm ci && npm run build'
        );
    }

    /**
     * Database connectivity, migrations, and queue backlog.
     */
    protected function checkDatabase(): void
    {
        try {
            DB::connection()->getPdo();
            $this->record('Database connection', 'pass', 'connected');
        } catch (Throwable $e) {
            $this->record('Database connection', 'fail', $e->getMessage());

            return;
        }

        try {
            if (! Schema::hasTable('migrations')) {
                $this->record('Migrations', 'fail', 'migrations table is missing; run php artisan migrate');

                return;
            }

            $ran = DB::table('migrations')->pluck('migration')->all();
            $files = array_map(
                fn (string $file) => basename($file, '.php'),
                glob(database_path('migrations/*.php')) ?: []
            );
            $pending = array_values(array_diff($files, $ran));

            $this->record(
                'Migrations',
                $pending === [] ? 'pass' : 'fail',
                $pending === [] ? count($ran).' applied' : count($pending).' pending; run php artisan migrate'
            );
        } catch (Throwable $e) {
            $this->record('Migrations', 'warn', 'could not inspect: '.$e->getMessage());
        }

        if ($this->option('skip-queue')) {
            return;
        }

        try {
            $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
            $this->record(
                'Failed jobs',
                $failed === 0 ? 'pass' : 'warn',
                $failed === 0 ? 'none' : "{$failed} failed job(s); run php artisan queue:failed"
            );

            if (config('queue.default') === 'database' && Schema::hasTable('jobs')) {
                $pending = DB::table('jobs')->count();
                $this->record(
                    'Queue backlog',
                    $pending < 100 ? 'pass' : 'warn',
                    $pending.' job(s) waiting'
                );
            }
        } catch (Throwable $e) {
            $this->record('Queue', 'warn', 'could not inspect: '.$e->getMessage());
        }
    }

    /**
     * Passport keys, required by the API and MCP guards.
     */
    protected function checkOauthKeys(): void
    {
        if (! class_exists(Passport::class)) {
            return;
        }

        $hasConfigKeys = filled(config('passport.private_key')) && filled(config('passport.public_key'));
        $keyPath = Passport::keyPath('oauth-private.key');

        $present = $hasConfigKeys
            || (is_file($keyPath) && is_file(Passport::keyPath('oauth-public.key')));

        $this->record(
            'OAuth keys',
            $present ? 'pass' : 'warn',
            $present ? 'present' : 'missing; run php artisan passport:keys (API and MCP auth will fail)'
        );
    }

    /**
     * Release identity, when running from a git checkout.
     */
    protected function checkVersion(): void
    {
        if (! is_dir(base_path('.git'))) {
            return;
        }

        $describe = @shell_exec('git -C '.escapeshellarg(base_path()).' describe --tags --always --dirty 2>&1');
        $describe = is_string($describe) ? trim($describe) : '';

        if ($describe !== '') {
            $this->record('Release', 'pass', $describe);
        }
    }

    /**
     * Record a single check result.
     */
    protected function record(string $name, string $status, string $detail): void
    {
        $this->checks[] = ['name' => $name, 'status' => $status, 'detail' => $detail];
    }

    /**
     * Count results with the given status.
     */
    protected function countByStatus(string $status): int
    {
        return count(array_filter($this->checks, fn (array $check) => $check['status'] === $status));
    }

    /**
     * Render results as a table.
     */
    protected function renderTable(): void
    {
        $labels = ['pass' => '<fg=green>PASS</>', 'warn' => '<fg=yellow>WARN</>', 'fail' => '<fg=red>FAIL</>'];

        $this->newLine();
        $this->line('<fg=cyan>OpenGRC doctor</>');
        $this->newLine();

        $this->table(
            ['Check', 'Status', 'Detail'],
            array_map(fn (array $check) => [$check['name'], $labels[$check['status']] ?? $check['status'], $check['detail']], $this->checks)
        );
    }

    /**
     * Render the summary line and any guidance.
     */
    protected function renderSummary(int $failures, int $warnings): void
    {
        $this->line(sprintf(
            'Pass: %d   Warn: %d   Fail: %d',
            $this->countByStatus('pass'),
            $warnings,
            $failures
        ));

        if ($failures > 0) {
            $this->error('Doctor found failures. Fix them before upgrading or deploying.');
        } elseif ($warnings > 0) {
            $this->warn('Doctor found warnings. Review them before upgrading or deploying.');
        } else {
            $this->info('All checks passed.');
        }

        $this->newLine();
    }
}
