<?php

namespace App\Console\Commands;

use App\Services\Scoping\FrameworkScopingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ScopeOrganization extends Command
{
    protected $signature = 'opengrc:scope
                            {profile : Path to a JSON organization profile}
                            {--output= : Write the assessment JSON to a file}';

    protected $description = 'Recommend frameworks, controls, documents, evidence and automation from an organization profile';

    public function handle(FrameworkScopingService $scoping): int
    {
        $path = (string) $this->argument('profile');

        if (! File::exists($path)) {
            $this->error("Profile not found: {$path}");

            return self::FAILURE;
        }

        $profile = json_decode((string) File::get($path), true);

        if (! is_array($profile)) {
            $this->error('Profile must be a JSON object.');

            return self::FAILURE;
        }

        $assessment = $scoping->assess($profile);
        $json = json_encode($assessment, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($output = $this->option('output')) {
            File::put((string) $output, $json.PHP_EOL);
            $this->info("Assessment written to {$output}");

            return self::SUCCESS;
        }

        $this->line($json);

        return self::SUCCESS;
    }
}
