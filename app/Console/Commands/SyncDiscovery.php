<?php

namespace App\Console\Commands;

use App\Services\Discovery\DiscoveryClient;
use App\Services\Discovery\DiscoverySync;
use Illuminate\Console\Command;
use Throwable;

class SyncDiscovery extends Command
{
    protected $signature = 'opengrc:discovery:sync';

    protected $description = 'Mirror read-only scan and finding summaries from OpenDiscoveryEngine';

    public function handle(DiscoveryClient $client, DiscoverySync $sync): int
    {
        if (! $client->configured()) {
            $this->error(
                'OpenDiscoveryEngine is not configured. '
                .'Set DISCOVERY_CONSOLE_URL, DISCOVERY_CONSOLE_TOKEN and DISCOVERY_CONSOLE_TENANT.'
            );

            return self::FAILURE;
        }

        try {
            $counts = $sync->run();
        } catch (Throwable $exception) {
            $this->error('Discovery sync failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Object', 'Seen', 'Created', 'Updated', 'Skipped'],
            [
                [
                    'Scans',
                    $counts['scans_seen'],
                    $counts['scans_created'],
                    $counts['scans_updated'],
                    $counts['scans_skipped'],
                ],
                [
                    'Findings',
                    $counts['findings_seen'],
                    $counts['findings_created'],
                    $counts['findings_updated'],
                    $counts['findings_skipped'],
                ],
            ]
        );

        if ($counts['findings_unlinked'] > 0) {
            $this->warn(
                $counts['findings_unlinked'].' finding(s) reference a scan that is not mirrored yet.'
            );
        }

        foreach ($counts['errors'] as $error) {
            $this->warn($error);
        }

        return $counts['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
