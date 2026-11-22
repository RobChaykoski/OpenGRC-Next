<?php

namespace App\Console\Commands;

use App\Enums\EvidenceSourceType;
use App\Models\AccessRule;
use App\Models\DiscoveryScan;
use App\Models\DocumentTemplate;
use App\Models\EvidenceSource;
use App\Models\User;
use App\Services\Audit\AuditLedger;
use App\Services\Evidence\ConnectorRegistry;
use App\Services\Evidence\EvidenceLedger;
use App\Support\DatabasePath;
use App\Support\InstanceMode;
use App\Support\MigrationManifest;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
        $this->checkDocumentTemplates();
        $this->checkBuild();
        $this->checkDatabase();
        $this->checkMigrationIntegrity();
        $this->checkEvidenceIntegrity();
        $this->checkAuditLedger();
        $this->checkEvidencePipeline();
        $this->checkDiscoveryConsole();
        $this->checkOauthKeys();
        $this->checkPrivilegedMfa();
        $this->checkAccessRules();
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

        $this->checkUrlSourceOfTruth();
    }

    /**
     * The settings table overrides APP_URL at boot, so editing .env alone can look
     * like it did nothing. Surface the divergence instead of letting someone
     * chase it.
     */
    protected function checkUrlSourceOfTruth(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }

            $settingUrl = setting('general.url');
        } catch (Throwable) {
            return;
        }

        $envUrl = $this->envFileValue('APP_URL');

        if (! is_string($settingUrl) || ! is_string($envUrl) || $settingUrl === '') {
            return;
        }

        if (rtrim($settingUrl, '/') !== rtrim($envUrl, '/')) {
            $this->record(
                'URL source of truth',
                'warn',
                "settings general.url ({$settingUrl}) overrides APP_URL ({$envUrl})"
            );
        }
    }

    /**
     * Read a value from the .env file without going through env().
     *
     * Larastan forbids env() outside config because it returns null when the
     * config is cached. Reading the file is also the only way to see what a
     * deployment actually wrote there.
     */
    protected function envFileValue(string $key): ?string
    {
        $path = base_path('.env');

        if (! is_file($path)) {
            return null;
        }

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            $line = trim($line);

            if (! str_starts_with($line, $key.'=')) {
                continue;
            }

            $value = trim(substr($line, strlen($key) + 1));

            return trim($value, '"\'');
        }

        return null;
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
     * Document template originals must still be on the private disk. A restored
     * database without the storage volume leaves rows pointing at nothing.
     */
    protected function checkDocumentTemplates(): void
    {
        try {
            if (! Schema::hasTable('document_templates')) {
                return;
            }

            $templates = DocumentTemplate::query()->get(['id', 'source_file', 'body_text']);

            if ($templates->isEmpty()) {
                return;
            }

            $disk = Storage::disk('private');
            $missing = $templates->filter(fn (DocumentTemplate $template): bool => ! is_string($template->source_file)
                || $template->source_file === ''
                || ! $disk->exists($template->source_file));

            $this->record(
                'Document template files',
                $missing->isEmpty() ? 'pass' : 'fail',
                $missing->isEmpty()
                    ? $templates->count().' template(s) present'
                    : $missing->count().' template(s) missing their source file',
            );

            $unparsed = $templates->filter(fn (DocumentTemplate $template): bool => blank($template->body_text));

            $this->record(
                'Document template text',
                $unparsed->isEmpty() ? 'pass' : 'warn',
                $unparsed->isEmpty()
                    ? 'extracted text present'
                    : $unparsed->count().' template(s) have no extracted text; edit and save to re-parse',
            );
        } catch (Throwable $e) {
            $this->record('Document template files', 'warn', 'could not inspect: '.$e->getMessage());
        }
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

                $retryAfter = (int) config('queue.connections.database.retry_after', 90);
                $stale = DB::table('jobs')
                    ->whereNotNull('reserved_at')
                    ->where('reserved_at', '<', now()->subSeconds($retryAfter * 2)->timestamp)
                    ->count();

                if ($stale > 0) {
                    $this->record(
                        'Queue worker',
                        'warn',
                        "{$stale} job(s) reserved but not progressing; is a worker running?"
                    );
                }
            }
        } catch (Throwable $e) {
            $this->record('Queue', 'warn', 'could not inspect: '.$e->getMessage());
        }
    }

    /**
     * Passport keys, required by the API guard.
     */
    /**
     * Migration files and applied checksums must agree with the committed
     * manifest. This is the change-control check, not a migration runner.
     */
    protected function checkMigrationIntegrity(): void
    {
        $errors = MigrationManifest::verifyFiles();

        if ($errors !== []) {
            $this->record('Migration integrity', 'fail', $errors[0]);

            return;
        }

        try {
            if (! Schema::hasTable('migrations') || ! Schema::hasColumn('migrations', 'checksum')) {
                $this->record('Migration integrity', 'warn', 'applied checksums are not recorded yet');

                return;
            }

            $checksums = MigrationManifest::checksums();
            $mismatch = DB::table('migrations')
                ->whereNotNull('checksum')
                ->get()
                ->filter(fn ($row) => isset($checksums[$row->migration])
                    && ! hash_equals((string) $checksums[$row->migration], (string) $row->checksum))
                ->count();

            $this->record(
                'Migration integrity',
                $mismatch === 0 ? 'pass' : 'fail',
                $mismatch === 0
                    ? count($checksums).' migrations match the manifest'
                    : "{$mismatch} applied migration checksum(s) differ from the manifest"
            );
        } catch (Throwable $e) {
            $this->record('Migration integrity', 'warn', 'could not inspect: '.$e->getMessage());
        }
    }

    /**
     * In production every file-bearing evidence record must have a ledger entry
     * and no withdrawal may be missing its reason.
     */
    /**
     * The audit ledger must verify. A broken chain or an altered activity row is
     * a failure; a pruned activity row is expected and only counted.
     */
    protected function checkAuditLedger(): void
    {
        try {
            if (! Schema::hasTable('audit_ledger_entries')) {
                $this->record('Audit ledger', 'warn', 'audit_ledger_entries table is missing; run migrations');

                return;
            }

            $report = app(AuditLedger::class)->verify();
        } catch (Throwable) {
            $this->record('Audit ledger', 'fail', 'could not verify the audit ledger');

            return;
        }

        if ($report['broken'] !== null) {
            $this->record('Audit ledger', 'fail', "chain broken at entry #{$report['broken']}");

            return;
        }

        if ($report['altered'] > 0) {
            $this->record('Audit ledger', 'fail', "{$report['altered']} activity row(s) differ from the ledger");

            return;
        }

        $detail = $report['total'].' entries, chain intact';

        if ($report['missing'] > 0) {
            $detail .= ", {$report['missing']} pruned";
        }

        $this->record('Audit ledger', 'pass', $detail);
    }

    protected function checkEvidenceIntegrity(): void
    {
        try {
            if (! Schema::hasTable('evidence_ledger_entries') || ! Schema::hasTable('evidence_states')) {
                $this->record('Evidence ledger', 'warn', 'ledger tables are missing; run migrations');

                return;
            }

            if (! InstanceMode::isProduction()) {
                $this->record('Evidence ledger', 'pass', 'testing mode; immutability not enforced');

                return;
            }

            $missing = 0;

            foreach (EvidenceLedger::SUBJECTS as $class) {
                $model = new $class;
                $column = $model->evidencePathColumn();

                $missing += $class::query()
                    ->whereNotNull($column)
                    ->where($column, '!=', '')
                    ->whereDoesntHave('ledgerEntries')
                    ->count();
            }

            $unexplained = DB::table('evidence_states')
                ->where('status', 'withdrawn')
                ->where(fn ($query) => $query->whereNull('reason')->orWhere('reason', ''))
                ->count();

            $failures = $missing + $unexplained;

            $this->record(
                'Evidence ledger',
                $failures === 0 ? 'pass' : 'fail',
                $failures === 0
                    ? 'production mode; every evidence record is ledgered'
                    : "{$missing} unledgered, {$unexplained} unexplained withdrawal(s)"
            );
        } catch (Throwable $e) {
            $this->record('Evidence ledger', 'warn', 'could not inspect: '.$e->getMessage());
        }
    }

    /**
     * Enabled sources need a registered connector and a fresh run. A source
     * that has never run, or has gone stale, is a blind spot, not a pass.
     */
    protected function checkEvidencePipeline(): void
    {
        try {
            if (! Schema::hasTable('evidence_sources') || ! Schema::hasTable('evidence_records')) {
                $this->record('Evidence pipeline', 'warn', 'tables are missing; run migrations');

                return;
            }

            $sources = EvidenceSource::query()->where('enabled', true)->get();

            if ($sources->isEmpty()) {
                $this->record('Evidence pipeline', 'pass', 'no enabled sources');

                return;
            }

            $registry = app(ConnectorRegistry::class);
            $threshold = now()->subDays((int) config('evidence.stale_after_days', 7));
            $missing = [];
            $never = [];
            $stale = [];

            foreach ($sources as $source) {
                $type = $source->type;
                $key = $type instanceof EvidenceSourceType ? $type->value : (string) $type;

                if (! $registry->has($key)) {
                    $missing[] = "{$source->name} ({$key})";
                }

                if ($source->last_run_at === null) {
                    $never[] = $source->name;
                } elseif ($source->last_run_at->lt($threshold)) {
                    $stale[] = $source->name;
                }
            }

            $problems = [];

            if ($missing !== []) {
                $problems[] = count($missing).' without a connector ('.implode(', ', array_slice($missing, 0, 3)).')';
            }

            if ($never !== []) {
                $problems[] = count($never).' never collected';
            }

            if ($stale !== []) {
                $problems[] = count($stale).' stale';
            }

            $this->record(
                'Evidence pipeline',
                $problems === [] ? 'pass' : 'warn',
                $problems === []
                    ? count($sources).' enabled source(s), all with a connector and a fresh run'
                    : implode('; ', $problems)
            );
        } catch (Throwable $exception) {
            $this->record('Evidence pipeline', 'warn', 'could not inspect: '.$exception->getMessage());
        }
    }

    /**
     * The discovery mirror must be configured deliberately and must not look
     * empty when the last sync failed. Doctor reports configuration and the age
     * of the newest synced row; it never calls the engine, so an engine outage
     * cannot hang a health check.
     */
    protected function checkDiscoveryConsole(): void
    {
        try {
            $config = config('services.discovery');
            $configured = is_array($config)
                && filled($config['base_url'] ?? null)
                && filled($config['token'] ?? null)
                && filled($config['tenant'] ?? null);

            if (! $configured) {
                $this->record('Discovery console', 'pass', 'not configured; optional');

                return;
            }

            if (! Schema::hasTable('discovery_scans')) {
                $this->record(
                    'Discovery console',
                    'warn',
                    'configured but the mirror tables are missing; run migrations'
                );

                return;
            }

            $lastSync = DiscoveryScan::query()->max('synced_at');

            if (! is_string($lastSync) || $lastSync === '') {
                $this->record(
                    'Discovery console',
                    'warn',
                    'configured but never synced; run php artisan opengrc:discovery:sync'
                );

                return;
            }

            $lastSyncAt = CarbonImmutable::parse($lastSync);
            $staleAfter = max(1, (int) ($config['stale_after_days'] ?? 2));

            if ($lastSyncAt->lt(now()->subDays($staleAfter))) {
                $this->record(
                    'Discovery console',
                    'warn',
                    'mirror is stale; last synced '.$lastSyncAt->diffForHumans()
                );

                return;
            }

            $this->record(
                'Discovery console',
                'pass',
                'mirror synced '.$lastSyncAt->diffForHumans()
            );
        } catch (Throwable $exception) {
            $this->record('Discovery console', 'warn', 'could not inspect: '.$exception->getMessage());
        }
    }

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
            $present ? 'present' : 'missing; run php artisan passport:keys (API auth will fail)'
        );
    }

    /**
     * Privileged accounts should have confirmed two-factor authentication.
     */
    protected function checkPrivilegedMfa(): void
    {
        try {
            if (! Schema::hasTable('users') || ! Schema::hasTable('breezy_sessions')) {
                return;
            }

            $privileged = User::query()->where('role', 'admin')->get();
        } catch (Throwable) {
            return;
        }

        if ($privileged->isEmpty()) {
            $this->record('Privileged MFA', 'pass', 'no privileged accounts yet');

            return;
        }

        $missing = $privileged->reject(fn ($user) => $user->hasConfirmedTwoFactor());

        $this->record(
            'Privileged MFA',
            $missing->isEmpty() ? 'pass' : 'warn',
            $missing->isEmpty()
                ? $privileged->count().' privileged account(s) enrolled'
                : $missing->count().' privileged account(s) without 2FA: '.$missing->pluck('email')->take(5)->implode(', ')
        );
    }

    /**
     * A disabled kill switch is a security exception, not a deployment mode.
     */
    protected function checkAccessRules(): void
    {
        try {
            if (! Schema::hasTable('access_rules')) {
                $this->record('Access rules', 'warn', 'access_rules table is missing; run migrations');

                return;
            }

            if (! (bool) config('security.access_rules.enabled', true)) {
                $this->record('Access rules', 'warn', 'disabled by ACCESS_RULES_ENABLED; enable after recovery');

                return;
            }

            $active = AccessRule::query()->active()->count();

            $this->record('Access rules', 'pass', $active.' active rule(s)');
        } catch (Throwable $exception) {
            $this->record('Access rules', 'warn', 'could not inspect: '.$exception->getMessage());
        }
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
