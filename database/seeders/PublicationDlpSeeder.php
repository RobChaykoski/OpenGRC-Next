<?php

namespace Database\Seeders;

use App\Models\DataClassification;
use App\Models\DlpPolicy;
use Illuminate\Database\Seeder;

/**
 * Publication DLP controls for the privacy / answer-key workstream.
 *
 * Run on an existing installation with:
 *
 *     php artisan db:seed --class=PublicationDlpSeeder
 *
 * This is a seeder rather than a migration so test databases are not given
 * policy rows implicitly. Existing policies keep their owner and review date.
 */
class PublicationDlpSeeder extends Seeder
{
    private const CLASSIFICATIONS = [
        [
            'code' => 'infrastructure_metadata',
            'label' => 'Infrastructure Metadata',
            'level' => 'confidential',
            'description' => 'IP addresses, internal hostnames, network topology, and deployment identifiers.',
        ],
        [
            'code' => 'assessment_answer_key',
            'label' => 'Assessment Answer Key',
            'level' => 'restricted',
            'description' => 'Lab flags and expected answer values that must not be published.',
        ],
        [
            'code' => 'secret_material',
            'label' => 'Secret Material',
            'level' => 'restricted',
            'description' => 'Credentials, API tokens, private keys, and connection strings.',
        ],
    ];

    private const POLICIES = [
        [
            'code' => 'PUB-001',
            'name' => 'Public repository publication gate',
            'description' => 'Blocks answer keys, secrets, and unreviewed infrastructure metadata from entering a public repository.',
            'channels' => ['repository', 'documentation', 'upload'],
            'classifications' => ['confidential', 'restricted'],
            'identifier_types' => [
                'infrastructure_metadata',
                'assessment_answer_key',
                'private_key',
                'aws_access_key',
                'secret_assignment',
                'credential_generic',
            ],
            'action' => 'block',
            'notes' => 'Workstream: publication privacy and DLP. Detectors: private_ipv4, public_ipv4, internal_hostname, cloud_metadata_endpoint, assessment_flag, api_key_assignment.',
        ],
        [
            'code' => 'PUB-002',
            'name' => 'Assessment answer-key export',
            'description' => 'Prevents lab flags and expected answer values from leaving the assessment boundary.',
            'channels' => ['upload', 'cloud', 'web', 'email'],
            'classifications' => ['restricted'],
            'identifier_types' => ['assessment_answer_key'],
            'action' => 'block',
            'notes' => 'Flags are answer keys. Documentation must use placeholders; only the lab definition and runtime may hold the value.',
        ],
        [
            'code' => 'PUB-003',
            'name' => 'Infrastructure metadata disclosure',
            'description' => 'Alerts on private addresses, internal hostnames, and deployment topology in documentation or outbound channels.',
            'channels' => ['repository', 'documentation', 'email', 'web'],
            'classifications' => ['confidential'],
            'identifier_types' => ['infrastructure_metadata'],
            'action' => 'alert',
            'notes' => 'Redact or replace with documentation ranges before publication. Review whether the address is already public and whether the exposure changes the attack surface.',
        ],
        [
            'code' => 'PUB-004',
            'name' => 'Secret material exposure',
            'description' => 'Quarantines credentials, API tokens, and private keys found in publication or outbound channels.',
            'channels' => ['repository', 'documentation', 'email', 'cloud', 'upload', 'web', 'ai'],
            'classifications' => ['restricted'],
            'identifier_types' => [
                'private_key',
                'aws_access_key',
                'secret_assignment',
                'credential_generic',
            ],
            'action' => 'quarantine',
            'notes' => 'Rotate the secret first, then remove it from history and notify the owner. A finding is not closed by deleting the file.',
        ],
    ];

    public function run(): void
    {
        foreach (self::CLASSIFICATIONS as $label) {
            DataClassification::query()->updateOrCreate(
                ['code' => $label['code']],
                [
                    'label' => $label['label'],
                    'level' => $label['level'],
                    'description' => $label['description'],
                    'industries' => null,
                    'is_system' => true,
                ],
            );
        }

        foreach (self::POLICIES as $definition) {
            $policy = DlpPolicy::query()->where('code', $definition['code'])->first();

            if ($policy) {
                $policy->update([
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'channels' => $definition['channels'],
                    'classifications' => $definition['classifications'],
                    'identifier_types' => $definition['identifier_types'],
                    'action' => $definition['action'],
                    'is_active' => true,
                    'notes' => $definition['notes'],
                ]);

                continue;
            }

            DlpPolicy::query()->create([
                'code' => $definition['code'],
                'name' => $definition['name'],
                'description' => $definition['description'],
                'channels' => $definition['channels'],
                'classifications' => $definition['classifications'],
                'identifier_types' => $definition['identifier_types'],
                'action' => $definition['action'],
                'owner_id' => null,
                'review_due_at' => now()->addDays(90)->toDateString(),
                'is_active' => true,
                'notes' => $definition['notes'],
            ]);
        }
    }
}
