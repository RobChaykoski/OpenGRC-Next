<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Filament\Resources\ImplementationResource;
use App\Models\Implementation;
use App\Models\User;
use App\Notifications\DropdownNotification;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Nudge implementation owners when a control test enters the due window, and
 * again when it lapses.
 *
 * Delivery is the Filament database notification the panels already poll, so a
 * reminder does not depend on a mail server the programme may not watch. Each
 * implementation is reminded at most once per stage and next-test date; the
 * dedupe is a cache marker, so a cache flush can repeat a reminder but never
 * drops one.
 *
 * Escalation is opt-in (config/control_testing.php). When enabled, an assigned
 * test that stays overdue past the threshold is raised to every administrator
 * once per test date, and unowned tests past the threshold become one weekly
 * digest per administrator. Nothing is escalated by default: the threshold is a
 * policy the institution must set.
 *
 * The message carries the code, title, date and owner name only. Notes and test
 * procedure stay on the implementation, so a notification never becomes a second
 * copy of the evidence.
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
     *     escalation_after_days: int,
     *     escalation_admins: int,
     *     would_notify: int,
     *     would_escalate: int,
     *     escalated: int,
     *     escalation_skipped: int,
     *     dry_run: bool
     * }
     */
    public function send(int $days = 7, bool $dryRun = false): array
    {
        $days = max(0, $days);
        $today = CarbonImmutable::today();
        $windowEnd = $today->addDays($days)->toDateString();
        $escalateAfter = max(0, (int) config('control_testing.escalation.after_days_overdue', 0));

        $unowned = Implementation::query()
            ->whereNull('implementation_owner_id')
            ->whereNotNull('next_test_at')
            ->whereDate('next_test_at', '<=', $windowEnd)
            ->orderBy('next_test_at')
            ->get();

        $admins = $escalateAfter > 0 ? $this->admins() : collect();

        $report = [
            'window_days' => $days,
            'due' => 0,
            'overdue' => 0,
            'notified' => 0,
            'skipped' => 0,
            'unassigned' => $unowned->count(),
            'escalation_after_days' => $escalateAfter,
            'escalation_admins' => $admins->count(),
            'would_notify' => 0,
            'would_escalate' => 0,
            'escalated' => 0,
            'escalation_skipped' => 0,
            'dry_run' => $dryRun,
        ];

        Implementation::query()
            ->with('implementationOwner')
            ->whereNotNull('implementation_owner_id')
            ->whereNotNull('next_test_at')
            ->whereDate('next_test_at', '<=', $windowEnd)
            ->orderBy('next_test_at')
            ->each(function (Implementation $implementation) use (
                &$report,
                $today,
                $dryRun,
                $escalateAfter,
                $admins,
            ): void {
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
                } elseif ($dryRun) {
                    $report['would_notify']++;
                } else {
                    $owner->notify($this->notification($implementation, $stage, $due, $daysLate));
                    Cache::put($key, true, $today->addDays(self::DEDUPE_TTL_DAYS));
                    $report['notified']++;
                }

                if (! $overdue || $escalateAfter === 0 || $daysLate < $escalateAfter) {
                    return;
                }

                $this->escalate($implementation, $owner, $due, $daysLate, $admins, $dryRun, $report);
            });

        if ($escalateAfter > 0 && $unowned->isNotEmpty()) {
            $this->escalateUnowned($unowned, $today, $escalateAfter, $admins, $dryRun, $report);
        }

        return $report;
    }

    /**
     * @param  Collection<int, User>  $admins
     * @param  array<string, mixed>  $report
     */
    private function escalate(
        Implementation $implementation,
        User $owner,
        CarbonImmutable $due,
        int $daysLate,
        Collection $admins,
        bool $dryRun,
        array &$report,
    ): void {
        $key = sprintf(
            'opengrc.control-test-escalation.%s.%s',
            (string) $implementation->getKey(),
            $due->toDateString(),
        );

        if (Cache::has($key)) {
            $report['escalation_skipped']++;

            return;
        }

        if ($admins->isEmpty()) {
            $report['escalation_skipped']++;

            return;
        }

        if ($dryRun) {
            $report['would_escalate']++;

            return;
        }

        $report['escalated']++;

        foreach ($admins as $admin) {
            $admin->notify(new DropdownNotification(
                title: (string) __('implementation.reminder.escalation_title'),
                body: trans_choice('implementation.reminder.escalation_body', $daysLate, [
                    'label' => $this->label($implementation),
                    'days' => $daysLate,
                    'owner' => $owner->name,
                ]),
                icon: 'heroicon-o-exclamation-triangle',
                color: 'danger',
                actionUrl: ImplementationResource::getUrl('view', ['record' => $implementation], panel: 'app'),
                actionLabel: (string) __('implementation.reminder.action'),
            ));
        }

        Cache::put($key, true, $due->addDays(self::DEDUPE_TTL_DAYS));
    }

    /**
     * @param  Collection<int, Implementation>  $unowned
     * @param  Collection<int, User>  $admins
     * @param  array<string, mixed>  $report
     */
    private function escalateUnowned(
        Collection $unowned,
        CarbonImmutable $today,
        int $escalateAfter,
        Collection $admins,
        bool $dryRun,
        array &$report,
    ): void {
        $overdue = $unowned->filter(function (Implementation $implementation) use ($today, $escalateAfter): bool {
            $due = CarbonImmutable::parse($implementation->next_test_at->toDateString())->startOfDay();

            return $due->lt($today) && (int) $due->diffInDays($today) >= $escalateAfter;
        })->values();

        if ($overdue->isEmpty()) {
            return;
        }

        if ($admins->isEmpty()) {
            $report['escalation_skipped'] += $overdue->count();

            return;
        }

        $week = sprintf('%s-W%02d', $today->format('o'), $today->isoWeek());
        $sent = false;

        foreach ($admins as $admin) {
            $key = sprintf('opengrc.control-test-escalation.unowned.%s.%s', (string) $admin->getKey(), $week);

            if (Cache::has($key)) {
                continue;
            }

            $sent = true;

            if ($dryRun) {
                continue;
            }

            $admin->notify(new DropdownNotification(
                title: (string) __('implementation.reminder.unowned_title'),
                body: trans_choice('implementation.reminder.unowned_body', $overdue->count(), [
                    'count' => $overdue->count(),
                    'items' => $this->digest($overdue),
                ]),
                icon: 'heroicon-o-user-minus',
                color: 'danger',
                actionUrl: ImplementationResource::getUrl('index', panel: 'app'),
                actionLabel: (string) __('implementation.reminder.unowned_action'),
            ));

            Cache::put($key, true, $today->addDays(8));
        }

        if ($sent) {
            if ($dryRun) {
                $report['would_escalate'] += $overdue->count();
            } else {
                $report['escalated'] += $overdue->count();
            }
        } else {
            $report['escalation_skipped'] += $overdue->count();
        }
    }

    private function notification(
        Implementation $implementation,
        string $stage,
        CarbonImmutable $due,
        int $daysLate,
    ): DropdownNotification {
        return new DropdownNotification(
            title: (string) __('implementation.reminder.'.$stage.'_title'),
            body: $stage === 'overdue'
                ? trans_choice('implementation.reminder.overdue_body', $daysLate, [
                    'label' => $this->label($implementation),
                    'date' => $due->toDateString(),
                    'days' => $daysLate,
                ])
                : (string) __('implementation.reminder.due_body', [
                    'label' => $this->label($implementation),
                    'date' => $due->toDateString(),
                ]),
            icon: $stage === 'overdue' ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-clock',
            color: $stage === 'overdue' ? 'danger' : 'warning',
            actionUrl: ImplementationResource::getUrl('view', ['record' => $implementation], panel: 'app'),
            actionLabel: (string) __('implementation.reminder.action'),
        );
    }

    private function label(Implementation $implementation): string
    {
        $code = trim((string) $implementation->code);
        $title = trim((string) $implementation->title);

        return $code === '' ? $title : $code.' — '.$title;
    }

    /**
     * @param  Collection<int, Implementation>  $implementations
     */
    private function digest(Collection $implementations): string
    {
        $shown = $implementations->take(5)->map(fn (Implementation $implementation): string => $this->label($implementation));
        $items = $shown->implode('; ');
        $extra = $implementations->count() - $shown->count();

        if ($extra > 0) {
            $items .= '; '.__('implementation.reminder.more', ['count' => $extra]);
        }

        return $items;
    }

    /**
     * @return Collection<int, User>
     */
    private function admins(): Collection
    {
        return User::query()->where('role', UserRole::Admin->value)->get();
    }
}
