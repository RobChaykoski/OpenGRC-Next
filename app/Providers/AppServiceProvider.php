<?php

namespace App\Providers;

use App\Enums\EvidenceSourceType;
use App\Livewire\CustomSessionGuard;
use App\Models\AccessRule;
use App\Models\AuditItem;
use App\Models\ControlValidationRule;
use App\Models\DataRequest;
use App\Models\DataRequestResponse;
use App\Models\DlpFinding;
use App\Models\DocumentTemplate;
use App\Models\EvidenceRecord;
use App\Models\EvidenceSource;
use App\Models\FileAttachment;
use App\Models\Finding;
use App\Models\Survey;
use App\Models\User;
use App\Models\VendorDocument;
use App\Models\VendorUser;
use App\Policies\AccessRulePolicy;
use App\Policies\AppPolicy;
use App\Policies\AuditItemPolicy;
use App\Policies\ControlValidationRulePolicy;
use App\Policies\DataRequestPolicy;
use App\Policies\DataRequestResponsePolicy;
use App\Policies\DlpFindingPolicy;
use App\Policies\DocumentTemplatePolicy;
use App\Policies\EvidenceRecordPolicy;
use App\Policies\EvidenceSourcePolicy;
use App\Policies\ExportPolicy;
use App\Policies\FileAttachmentPolicy;
use App\Policies\FindingPolicy;
use App\Policies\SurveyPolicy;
use App\Policies\UserPolicy;
use App\Policies\VendorDocumentPolicy;
use App\Policies\VendorUserPolicy;
use App\Services\Evidence\ConnectorRegistry;
use App\Services\Evidence\Connectors\OpenMetadataConnector;
use App\Services\Evidence\Connectors\PatchMonConnector;
use App\Services\Evidence\Connectors\WazuhConnector;
use App\Services\HtmlSanitizerService;
use App\Support\Http\ReadOnlyHttp;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use BladeUI\Icons\Factory as IconFactory;
use Exception;
use Filament\Actions\Exports\Models\Export;
use Filament\Support\Facades\FilamentColor;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Livewire\Livewire;
use Log;
use MangoldSecurity\Settings\Services\Setting;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force secure session cookies whenever the app is served over HTTPS in
        // production. .env is not enough: a copy/paste deploy can silently drop
        // the flag and send the session cookie over plain HTTP.
        if (app()->environment('production') && config('security.force_secure_cookies', true)) {
            if (str_starts_with((string) config('app.url'), 'https://')) {
                Config::set('session.secure', true);
                Config::set('session.http_only', true);
            }
        }

        // Override the package's SessionGuard component with our custom one
        Livewire::component('filament-inactivity-guard::session-guard', CustomSessionGuard::class);

        // Passport is headless: it registers the browser authorise routes but no
        // longer ships views. Without these bindings /oauth/authorize and
        // /oauth/device fail while resolving the response contract. Point them
        // at the self-hosted consent pages in resources/views/oauth.
        Passport::viewPrefix('oauth');

        // Outbound evidence collection is read-only by construction.
        ReadOnlyHttp::register();

        // Render user-supplied rich text as sanitized HTML. Use these instead of {!! !!}.
        $this->app->singleton(HtmlSanitizerService::class);
        Blade::directive('safeHtml', fn (string $expression) => "<?php echo app(\\App\\Services\\HtmlSanitizerService::class)->sanitize({$expression}); ?>");
        Blade::directive('safePdfHtml', fn (string $expression) => "<?php echo app(\\App\\Services\\HtmlSanitizerService::class)->sanitizeForPdf({$expression}); ?>");

        // Disable mass assignment protection
        Model::unguard();

        // Only skip the install check if running the installer command or actual PHPUnit tests
        $isInstaller = false;
        if ($this->app->runningInConsole()) {
            $argv = $_SERVER['argv'] ?? [];
            $command = $argv[1] ?? null;

            $installerCommands = [
                'package:discover',
                'filament:upgrade',
                'vendor:publish',
                'test',
                'migrate',
                'config:cache',
                'config:clear',
                'route:cache',
                'route:clear',
                'view:cache',
                'view:clear',
                'cache:clear',
                'optimize',
                'optimize:clear',
                'key:generate',
                'storage:link',
                'db:seed',
                'passport:keys',
                'queue:work',
                'queue:restart',
                'about',
            ];

            // Every OpenGRC command must run before (or without) a working
            // database, otherwise the command that diagnoses a broken install
            // cannot start. The same applies to migration and queue plumbing.
            if ($command !== null && (
                str_starts_with($command, 'opengrc:')
                || str_starts_with($command, 'migrate:')
                || in_array($command, $installerCommands, true)
            )) {
                $isInstaller = true;
            }
        }

        // Skip settings config only when running actual PHPUnit tests (not just APP_ENV=testing)
        if ($this->app->runningUnitTests()) {
            $isInstaller = true;
        }

        if (! $isInstaller) {
            if (Schema::hasTable('settings')) {

                Config::set('app.name', setting('general.name', 'OpenGRC'));
                Config::set('app.url', setting('general.url', 'https://opengrc.test'));

                // Only override the .env mail config once SMTP settings have been
                // saved in-app; a fresh install must not boot the mailer with a
                // null host. Avoid the top-level "driver" key — it switches
                // MailManager to the legacy whole-array config path.
                $mailHost = setting('mail.host');
                if (! empty($mailHost)) {
                    // Decrypt mail password if it's encrypted
                    $mailPassword = setting('mail.password');
                    if (! empty($mailPassword)) {
                        try {
                            $mailPassword = Crypt::decryptString($mailPassword);
                        } catch (Exception $e) {
                            // If decryption fails, assume it's plaintext (legacy data)
                        }
                    }

                    config([
                        'mail.default' => 'smtp',
                        'mail.mailers.smtp' => array_merge(config('mail.mailers.smtp', []), [
                            'transport' => 'smtp',
                            'host' => $mailHost,
                            'port' => setting('mail.port', config('mail.mailers.smtp.port')),
                            'encryption' => setting('mail.encryption'),
                            'username' => setting('mail.username'),
                            'password' => $mailPassword,
                        ]),
                        'mail.from' => [
                            'address' => setting('mail.from', config('mail.from.address')),
                            'name' => setting('general.name'),
                        ],
                    ]);
                }

                // Configure filesystem based on settings
                $storageDriver = setting('storage.driver', 'private');

                // Ensure local disk is always configured
                config()->set('filesystems.disks.local', array_merge(config('filesystems.disks.local', []), [
                    'driver' => 'local',
                    'root' => storage_path('app'),
                    'throw' => false,
                ]));

                // Configure S3-compatible storage (AWS S3 or DigitalOcean Spaces)
                if (in_array($storageDriver, ['s3', 'digitalocean'])) {
                    $settingKey = "storage.{$storageDriver}";
                    $accessKey = setting("{$settingKey}.key");
                    $secretKey = setting("{$settingKey}.secret");
                    $region = setting("{$settingKey}.region", $storageDriver === 's3' ? 'us-east-1' : 'nyc3');
                    $bucket = setting("{$settingKey}.bucket");

                    try {
                        // Decrypt credentials if they exist and are encrypted
                        if (! empty($accessKey)) {
                            $accessKey = Crypt::decryptString($accessKey);
                        }
                        if (! empty($secretKey)) {
                            $secretKey = Crypt::decryptString($secretKey);
                        }

                        $diskConfig = [
                            'driver' => 's3',
                            'key' => $accessKey,
                            'secret' => $secretKey,
                            'bucket' => $bucket,
                        ];

                        if ($storageDriver === 'digitalocean') {
                            // DigitalOcean Spaces uses path-style endpoint
                            $diskConfig['region'] = 'us-east-1'; // Always us-east-1 for AWS SDK compatibility
                            $diskConfig['endpoint'] = 'https://'.strtolower($region).'.digitaloceanspaces.com';
                            $diskConfig['use_path_style_endpoint'] = true;
                        } else {
                            // AWS S3
                            $diskConfig['region'] = $region;
                            $diskConfig['use_path_style_endpoint'] = false;
                        }

                        config()->set("filesystems.disks.{$storageDriver}", array_merge(
                            config("filesystems.disks.{$storageDriver}", []),
                            $diskConfig
                        ));
                    } catch (Exception $e) {
                        Log::error("Failed to decrypt {$storageDriver} credentials: ".$e->getMessage());
                        $storageDriver = 'private';
                    }
                }

                // Set the default filesystem driver
                config()->set('filesystems.default', $storageDriver);

                // Set session lifetime from settings
                Config::set('session.lifetime', setting('security.session_timeout', 15));
            } else {
                // if table "settings" does not exist
                // Error that app was not installed properly
                abort(500, 'OpenGRC was not installed properly. Please review the
                installation guide at https://docs.opengrc.com to install the app.');
            }
        }

        Gate::before(function ($user, string $ability) {
            if (! $user instanceof User) {
                return null;
            }

            if ($user->isAdmin()) {
                return true;
            }

            // Permission-style abilities ("List Standards", "Manage Users")
            // remain in use by settings pages and widgets. Model abilities
            // ("viewAny", "update") have no space and fall through to a policy.
            if (! str_contains($ability, ' ')) {
                return null;
            }

            $verb = strtok($ability, ' ');

            if (in_array($verb, ['List', 'Read', 'View'], true)) {
                return true;
            }

            if (in_array($verb, ['Create', 'Update'], true)) {
                return $user->canWrite();
            }

            return false;
        });

        // One policy for ordinary models. Models with ownership or isolation
        // rules keep their own policy and are mapped here explicitly.
        Gate::guessPolicyNamesUsing(function (string $model): string {
            return match ($model) {
                AccessRule::class => AccessRulePolicy::class,
                AuditItem::class => AuditItemPolicy::class,
                ControlValidationRule::class => ControlValidationRulePolicy::class,
                DataRequest::class => DataRequestPolicy::class,
                DataRequestResponse::class => DataRequestResponsePolicy::class,
                DlpFinding::class => DlpFindingPolicy::class,
                DocumentTemplate::class => DocumentTemplatePolicy::class,
                EvidenceRecord::class => EvidenceRecordPolicy::class,
                EvidenceSource::class => EvidenceSourcePolicy::class,
                FileAttachment::class => FileAttachmentPolicy::class,
                Finding::class => FindingPolicy::class,
                Survey::class => SurveyPolicy::class,
                User::class => UserPolicy::class,
                VendorDocument::class => VendorDocumentPolicy::class,
                VendorUser::class => VendorUserPolicy::class,
                Export::class => ExportPolicy::class,
                default => AppPolicy::class,
            };
        });

        LanguageSwitch::configureUsing(function (LanguageSwitch $switch) {
            $switch
                ->locales(['en', 'es', 'fr', 'hr']);
        });

        Table::configureUsing(function (Table $table): Table {
            return $table->paginationPageOptions([10, 25, 50, 100]);
        });

        FilamentColor::register([
            'bg-grcblue' => [
                50 => '#eaf3f7',
                100 => '#d4e7ef',
                200 => '#a9cfe0',
                300 => '#7eb7d1',
                400 => '#1375a0',
                500 => '#106689',
                600 => '#0d5773',
                700 => '#0a485d',
                800 => '#374151',
                900 => '#212a3a',
            ],
            'danger' => [
                50 => '254, 242, 242',
                100 => '254, 226, 226',
                200 => '254, 202, 202',
                300 => '252, 165, 165',
                400 => '248, 113, 113',
                500 => '239, 68, 68',
                600 => '220, 38, 38',
                700 => '185, 28, 28',
                800 => '153, 27, 27',
                900 => '127, 29, 29',
                950 => '69, 10, 10',
            ],
        ]);

    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Connectors register against this singleton during boot.
        $this->app->singleton(ConnectorRegistry::class, function (): ConnectorRegistry {
            $registry = new ConnectorRegistry;
            $registry->register(EvidenceSourceType::Patchmon, PatchMonConnector::class);
            $registry->register(EvidenceSourceType::Wazuh, WazuhConnector::class);
            $registry->register(EvidenceSourceType::OpenMetadata, OpenMetadataConnector::class);

            return $registry;
        });

        /*
         * Follow the scheme in APP_URL (must be in register, not boot).
         *
         * Forcing https for every non-local environment used to redirect a
         * plain-HTTP install to a port that does not answer TLS, which made the
         * container unreachable. A deployment that terminates TLS sets an
         * https:// URL and gets https links; one that does not stays on http.
         * Proxy headers still mark the request secure when a terminator sets
         * X-Forwarded-Proto, so isSecure() and HSTS behave correctly either way.
         */
        if (! $this->app->environment('local') && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');

            // Ensure HTTPS is detected from proxy headers
            $this->app['request']->server->set('HTTPS', 'on');
            $_SERVER['HTTPS'] = 'on';
        }

        // Register custom icons
        $this->callAfterResolving(IconFactory::class, function (IconFactory $factory) {
            $factory->add('grc', [
                'path' => resource_path('svg'),
                'prefix' => 'grc',
            ]);
        });

        // Register setting service early so it's available for Filament panel providers
        // The mangoldsecurity/settings package registers this in boot() which is too late
        if (! $this->app->bound('setting')) {
            $this->app->singleton('setting', function () {
                return new Setting;
            });
        }
    }
}
