<?php

namespace App\Filament\Pages;

use App\Enums\ApprovalRequestStatus;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRequestStep;
use App\Models\User;
use App\Services\Controls\ApprovalWorkflow;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * The current user's actionable approval steps.
 *
 * Only the current pending step of a pending request is shown. A future step in
 * the same chain is not actionable yet.
 */
class MyApprovals extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Entities';

    protected static ?int $navigationSort = 48;

    protected static ?string $slug = 'my-approvals';

    protected string $view = 'filament.pages.my-approvals';

    /** @var array<int|string, string> */
    public array $comments = [];

    public static function getNavigationLabel(): string
    {
        return 'My approvals';
    }

    public function getTitle(): string
    {
        return 'My approvals';
    }

    public static function getNavigationBadge(): ?string
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return null;
        }

        $count = ApprovalRequestStep::query()
            ->pending()
            ->where('approver_id', $user->getKey())
            ->whereHas('request', fn ($query) => $query->where('status', ApprovalRequestStatus::Pending->value))
            ->get()
            ->filter(fn (ApprovalRequestStep $step): bool => $step->isCurrent())
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * @return Collection<int, ApprovalRequestStep>
     */
    public function approvals(): Collection
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return collect();
        }

        return ApprovalRequestStep::query()
            ->pending()
            ->where('approver_id', $user->getKey())
            ->whereHas('request', fn ($query) => $query->where('status', ApprovalRequestStatus::Pending->value))
            ->with(['request.requestedBy', 'request.steps'])
            ->get()
            ->filter(fn (ApprovalRequestStep $step): bool => $step->isCurrent())
            ->sortBy(fn (ApprovalRequestStep $step): int => $step->request?->due_at?->timestamp ?? PHP_INT_MAX)
            ->values();
    }

    public function approve(int $stepId): void
    {
        $this->respond($stepId, true);
    }

    public function reject(int $stepId): void
    {
        $this->respond($stepId, false);
    }

    protected function respond(int $stepId, bool $approve): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        $step = ApprovalRequestStep::query()
            ->pending()
            ->whereKey($stepId)
            ->where('approver_id', $user->getKey())
            ->whereHas('request', fn ($query) => $query->where('status', ApprovalRequestStatus::Pending->value))
            ->first();

        if (! $step instanceof ApprovalRequestStep || ! $step->isCurrent() || ! $step->request instanceof ApprovalRequest) {
            Notification::make()
                ->title('That approval step is not available.')
                ->danger()
                ->send();

            return;
        }

        $comment = trim((string) ($this->comments[$stepId] ?? ''));

        if (! $approve && $comment === '') {
            Notification::make()
                ->title('Add a reason before rejecting.')
                ->warning()
                ->send();

            return;
        }

        try {
            $workflow = app(ApprovalWorkflow::class);

            if ($approve) {
                $workflow->approve($step->request, $step, $user, $comment === '' ? null : $comment);
            } else {
                $workflow->reject($step->request, $step, $user, $comment);
            }
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        unset($this->comments[$stepId]);

        Notification::make()
            ->title($approve ? 'Step approved' : 'Step rejected')
            ->success()
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'approvals' => $this->approvals(),
        ];
    }
}
