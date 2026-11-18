<?php

namespace App\Console\Commands;

use App\Services\Scoping\ControlCatalogueSyncer;
use Illuminate\Console\Command;
use RuntimeException;

class SyncControlCatalogue extends Command
{
    protected $signature = 'opengrc:catalog:sync';

    protected $description = 'Seed canonical controls and framework mappings from config/framework_scoping.php';

    public function handle(ControlCatalogueSyncer $syncer): int
    {
        try {
            $result = $syncer->sync();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Catalogue', 'Created', 'Updated'],
            [
                ['Controls', $result['controls_created'], $result['controls_updated']],
                ['Frameworks', $result['frameworks_created'], $result['frameworks_updated']],
            ]
        );

        $this->line(sprintf(
            'Framework mappings: %d attached, %d detached.',
            $result['mappings_attached'],
            $result['mappings_detached']
        ));

        if ($result['stale_controls'] !== []) {
            $this->warn(sprintf(
                '%d canonical control(s) are no longer in config and were kept: %s',
                count($result['stale_controls']),
                implode(', ', $result['stale_controls'])
            ));
            $this->line('Review them, then retire them deliberately once nothing links to them.');
        }

        if ($result['unused_controls'] !== []) {
            $this->line(sprintf(
                '%d catalogue control(s) are not mapped to any framework: %s',
                count($result['unused_controls']),
                implode(', ', $result['unused_controls'])
            ));
        }

        $this->info('Catalogue synced.');

        return self::SUCCESS;
    }
}
