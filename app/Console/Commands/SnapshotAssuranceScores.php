<?php

namespace App\Console\Commands;

use App\Models\AssuranceSnapshot;
use App\Models\CanonicalFramework;
use App\Services\Scoping\AssuranceScore;
use Illuminate\Console\Command;

class SnapshotAssuranceScores extends Command
{
    protected $signature = 'opengrc:assurance:snapshot
                            {--framework= : Limit the snapshot to one framework code}';

    protected $description = 'Store today\'s assurance score for each framework, with its components';

    public function handle(AssuranceScore $scores): int
    {
        $frameworks = CanonicalFramework::query()
            ->when(
                $this->option('framework'),
                fn ($query, string $code) => $query->where('code', $code)
            )
            ->orderBy('priority')
            ->orderBy('name')
            ->get();

        if ($frameworks->isEmpty()) {
            $this->warn('No frameworks in the catalogue. Run opengrc:catalog:sync first.');

            return self::SUCCESS;
        }

        $today = now()->startOfDay();
        $rows = [];

        foreach ($frameworks as $framework) {
            $result = $scores->forFramework($framework->code);

            if ($result === null) {
                $rows[] = [$framework->code, '—', 'No outcomes for this framework'];

                continue;
            }

            AssuranceSnapshot::updateOrCreate(
                [
                    'framework_code' => $framework->code,
                    'snapshot_date' => $today,
                ],
                [
                    'score' => $result['score'],
                    'components' => $result['components'],
                    'counts' => $result['counts'],
                ]
            );

            $rows[] = [$framework->code, (string) $result['score'], $result['counts']['reviewed'].'/'.$result['outcomes'].' reviewed'];
        }

        $this->table(['Framework', 'Score', 'Detail'], $rows);
        $this->info('Assurance snapshot stored for '.$today->toDateString().'.');

        return self::SUCCESS;
    }
}
