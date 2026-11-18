<?php

namespace App\Console\Commands;

use App\Services\Scoping\ControlCatalogueLinker;
use Illuminate\Console\Command;
use RuntimeException;

class LinkControlCatalogue extends Command
{
    protected $signature = 'opengrc:catalog:link';

    protected $description = 'Apply curated links from imported controls to canonical outcomes';

    public function handle(ControlCatalogueLinker $linker): int
    {
        try {
            $result = $linker->link();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Result', 'Count'],
            [
                ['Standards with curated links', $result['standards_linked']],
                ['Links created', $result['links_created']],
                ['Links removed', $result['links_removed']],
                ['Controls linked', $result['controls_linked']],
                ['Controls not linked', $result['controls_unlinked']],
            ]
        );

        if ($result['standards_missing'] !== []) {
            $this->warn(sprintf(
                '%d framework(s) in the curated map are not installed: %s',
                count($result['standards_missing']),
                implode(', ', $result['standards_missing'])
            ));
            $this->line('Import them with php artisan opengrc:framework-import, then run this command again.');
        }

        if ($result['controls_missing'] !== []) {
            $this->warn(sprintf(
                '%d curated identifier(s) did not match an imported control: %s',
                count($result['controls_missing']),
                implode(', ', $result['controls_missing'])
            ));
            $this->line('The imported framework version may differ from the curated map.');
        }

        if ($result['controls_unlinked'] > 0) {
            $this->line(sprintf(
                '%d of %d controls have no canonical outcome. That is expected: links are curated, never guessed. '
                .'Link them on the control page when someone has reviewed the requirement.',
                $result['controls_unlinked'],
                $result['controls_total']
            ));
        }

        $this->info('Curated links applied.');

        return self::SUCCESS;
    }
}
