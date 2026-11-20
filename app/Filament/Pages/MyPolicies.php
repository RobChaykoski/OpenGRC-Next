<?php

namespace App\Filament\Pages;

use App\Filament\Resources\PolicyResource;
use App\Models\Policy;
use App\Models\PolicyAcknowledgement;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * The current user's policy acknowledgement queue.
 *
 * An acknowledgement is tied to a content revision. When a policy changes, the
 * previous acknowledgement is stale and the policy reappears here.
 */
class MyPolicies extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';

    protected static string|\UnitEnum|null $navigationGroup = 'Entities';

    protected static ?int $navigationSort = 45;

    protected static ?string $slug = 'my-policies';

    protected string $view = 'filament.pages.my-policies';

    public static function getNavigationLabel(): string
    {
        return 'My policies';
    }

    public function getTitle(): string
    {
        return 'My policies';
    }

    public static function getNavigationBadge(): ?string
    {
        $page = app(self::class);
        $outstanding = collect($page->policies())->where('current', false)->count();

        return $outstanding > 0 ? (string) $outstanding : null;
    }

    /**
     * @return array<int, array{policy: Policy, acknowledged_at: mixed, current: bool}>
     */
    public function policies(): array
    {
        $user = auth()->user();

        if ($user === null) {
            return [];
        }

        $policies = Policy::query()
            ->where(function ($query): void {
                $query->whereNull('retired_date')->orWhere('retired_date', '>=', today());
            })
            ->where(function ($query): void {
                $query->whereNull('effective_date')->orWhere('effective_date', '<=', today());
            })
            ->orderBy('name')
            ->get();

        $acknowledgements = PolicyAcknowledgement::query()
            ->where('user_id', $user->getKey())
            ->whereIn('policy_id', $policies->pluck('id'))
            ->orderByDesc('acknowledged_at')
            ->get()
            ->groupBy('policy_id');

        return $policies->map(function (Policy $policy) use ($acknowledgements): array {
            /** @var PolicyAcknowledgement|null $latest */
            $latest = $acknowledgements->get($policy->getKey())?->first();

            return [
                'policy' => $policy,
                'acknowledged_at' => $latest?->acknowledged_at,
                'current' => $latest !== null && $latest->policy_revision === $policy->currentRevision(),
            ];
        })->all();
    }

    public function acknowledge(int $policyId): void
    {
        $user = auth()->user();
        $policy = Policy::query()->find($policyId);

        if ($user === null || $policy === null || ! $policy->isActive()) {
            Notification::make()
                ->title('That policy is not available for acknowledgement.')
                ->danger()
                ->send();

            return;
        }

        PolicyAcknowledgement::query()->updateOrCreate(
            [
                'policy_id' => $policy->getKey(),
                'user_id' => $user->getKey(),
                'policy_revision' => $policy->currentRevision(),
            ],
            [
                'policy_updated_at' => $policy->updated_at,
                'acknowledged_at' => now(),
                'method' => 'web',
            ]
        );

        Notification::make()
            ->title('Policy acknowledged')
            ->body($policy->name)
            ->success()
            ->send();
    }

    /**
     * @return Collection<int, Policy>
     */
    public function policyOptions(): Collection
    {
        return Policy::query()->orderBy('name')->get();
    }

    public function policyUrl(Policy $policy): string
    {
        return PolicyResource::getUrl('view', ['record' => $policy]);
    }
}
