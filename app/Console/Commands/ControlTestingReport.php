<?php

namespace App\Console\Commands;

use App\Models\Implementation;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Report control implementations whose scheduled test is due or overdue.
 *
 * This is a report, not a reminder engine. Run it from cron or a monitoring
 * check; it does not send mail or change the implementation.
 */
class ControlTestingReport extends Command
{
    protected $signature = 'opengrc:controls:testing
                            {--days=0 : Include tests due within this many days}
                            {--owner= : Only implementations owned by this user ID}
                            {--json : Output machine-readable JSON}
                            {--fail-on-overdue : Exit non-zero when any test is overdue}';

    protected $description = 'List control implementations whose test is due or overdue';

    public function handle(): int
    {
        $days = max(0, (int) $this->option('days'));
        $ownerId = $this->option('owner');

        if ($ownerId !== null) {
            $owner = User::query()->find((int) $ownerId);

            if ($owner === null) {
                $this->error('No user found for that owner ID.');

                return self::FAILURE;
            }
        }

        $implementations = Implementation::query()
            ->with(['implementationOwner', 'controls'])
            ->whereNotNull('next_test_at')
            ->whereDate('next_test_at', '<=', today()->addDays($days))
            ->when($ownerId !== null, fn ($query) => $query->where('implementation_owner_id', (int) $ownerId))
            ->orderBy('next_test_at')
            ->get();

        $rows = $implementations->map(function (Implementation $implementation): array {
            $next = $implementation->next_test_at;
            $overdue = $next !== null && $next->lt(today());

            return [
                'code' => $implementation->code,
                'title' => $implementation->title,
                'owner' => $implementation->implementationOwner?->name,
                'next_test_at' => $next?->toDateString(),
                'days_overdue' => $overdue ? (int) $next->startOfDay()->diffInDays(today(), false) : 0,
                'overdue' => $overdue,
                'controls' => $implementation->controls->pluck('code')->filter()->implode(', '),
            ];
        });

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'generated_at' => now()->toIso8601String(),
                'window_days' => $days,
                'due' => $rows->count(),
                'overdue' => $rows->where('overdue', true)->count(),
                'implementations' => $rows->values()->all(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ($rows->isEmpty()) {
            $this->info($days === 0
                ? 'No control tests are due or overdue.'
                : "No control tests are due in the next {$days} day(s).");
        } else {
            $this->table(
                ['Implementation', 'Title', 'Owner', 'Next test', 'Days overdue', 'Controls'],
                $rows->map(fn (array $row): array => [
                    $row['code'],
                    $row['title'],
                    $row['owner'] ?? 'Unassigned',
                    $row['next_test_at'] ?? '—',
                    $row['overdue'] ? (string) $row['days_overdue'] : '—',
                    $row['controls'] === '' ? '—' : $row['controls'],
                ])->all()
            );

            $overdue = $rows->where('overdue', true)->count();
            $this->line("{$rows->count()} due; {$overdue} overdue.");
        }

        if ($this->option('fail-on-overdue') && $rows->where('overdue', true)->isNotEmpty()) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
