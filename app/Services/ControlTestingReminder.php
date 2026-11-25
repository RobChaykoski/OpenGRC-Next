<?php

namespace App\Services;

use App\Filament\Resources\ImplementationResource;
use App\Models\Implementation;
use App\Notifications\DropdownNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Nudge implementation owners when a control test enters the due window, and
 * again when it lapses.
 *
 * Delivery is the Filament database notification the panels already poll, so a
 * reminder does not depend on a mail server the programme may not watch. Each
 * implementation is reminded at most once per stage and next-test date; the
 * dedupe is a cache marker, so a cache flush can repeat a reminder but never
 * drops one. An implementation with no owner is counted and not routed: there
 * is nobody to remind, and closing the owner field is the fix.
 *
 * The message carries the code, title and date only. Notes and test procedure
 * stay on the implementation, so a notification never becomes a second copy of
 * the evidence.
 */
final class ControlTestingReminder
{
    public const DEDUPE_TTL_DAYS = 60;

    /**
     * @return array{
     *     window_days: int,
     *     due: int,
     *     overdue: int,
     *     notified: int,
     *     skipped: int,
     *     unassigned: int,
     *     dry_run: bool
     * }
     */
    public function send(int $days = 7, bool $dryRun = false): array
    {
        $days = max(0, $days);
        $today = CarbonImmutable::today();
        $windowEnd = $today->addDays($days)->toDateString();

        $report = [
            'window_days' => $days,
            'due' => 0,
            'overdue' => 0,
            'notified' => 0,
            'skipped' => 0,
            'unassigned' => Implementation::query()
                ->whereNull('implementation_owner_id')
                ->whereNotNull('next_test_at')
                ->whereDate('next_test_at', '<=', $windowEnd)
                ->count(),
            'dry_run' => $dryRun,
        ];

        Implementation::query()
            ->with('implementationOwner')
            ->whereNotNull('implementation_owner_id')
            ->whereNotNull('next_test_at')
            ->whereDate('next_test_at', '<=', $windowEnd)
            ->orderBy('next_test_at')
            ->each(function (Implementation $implementation) use (&$report, $today, $dryRun): void {
                $owner = $implementation->implementationOwner;
                $nextTest = $implementation->next_test_at;

                if ($owner === null || $nextTest === null) {
                    $report['unassigned']++;

                    return;
                }

                $due = CarbonImmutable::parse($nextTest->toDateString())->startOfDay();
                $overdue = $due->lt($today);
                $stage = $overdue ? 'overdue' : 'due';
                $daysLate = $overdue ? (int) $due->diffInDays($today) : 0;

                $report[$stage]++;

                $key = sprintf(
                    'opengrc.control-test-reminder.%s.%s.%s',
                    $stage,
                    (string) $implementation->getKey(),
                    $due->toDateString(),
                );

                if (Cache::has($key)) {
                    $report['skipped']++;

                    return;
                }

                if ($dryRun) {
                    return;
                }

                $code = trim((string) $implementation->code);
                $title = trim((string) $implementation->title);
                $label = $code === '' ? $title : $code.' — '.$title;

                $owner->notify(new DropdownNotification(
                    title: (string) __('implementation.reminder.'.$stage.'_title'),
                    body: $overdue
                        ? trans_choice('implementation.reminder.overdue_body', $daysLate, [
                            'label' => $label,
                            'date' => $due->toDateString(),
                            'days' => $daysLate,
                        ])
                        : (string) __('implementation.reminder.due_body', [
                            'label' => $label,
                            'date' => $due->toDateString(),
                        ]),
                    icon: $overdue ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-clock',
                    color: $overdue ? 'danger' : 'warning',
                    actionUrl: ImplementationResource::getUrl('view', ['record' => $implementation], panel: 'app'),
                    actionLabel: (string) __('implementation.reminder.action'),
                ));

                Cache::put($key, true, $today->addDays(self::DEDUPE_TTL_DAYS));
                $report['notified']++;
            });

        return $report;
    }
}
