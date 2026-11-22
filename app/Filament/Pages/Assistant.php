<?php

namespace App\Filament\Pages;

use App\Enums\AssistantIntent;
use App\Enums\AssistantRunStatus;
use App\Models\AiAssistantRun;
use App\Models\CanonicalFramework;
use App\Models\Implementation;
use App\Models\User;
use App\Services\Ai\Assistant\AssistantService;
use App\Services\Assistant\AssistantOperations;
use App\Services\Assistant\AssistantProposalService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Livewire\Attributes\Url;

/**
 * Read-only assistant over the GRC graph (ADR 0046).
 *
 * A question resolves to one reviewed query. The deterministic facts are shown
 * beside the AI narrative, so the reader can check the interpretation rather
 * than trust it. There is no write action and no free-form tool use.
 */
class Assistant extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?int $navigationSort = 5;

    protected static ?string $slug = 'assistant';

    protected string $view = 'filament.pages.assistant';

    #[Url]
    public ?string $question = null;

    #[Url]
    public ?string $framework = null;

    public ?string $answer = null;

    public ?string $notice = null;

    public ?string $status = null;

    public ?string $intent = null;

    public ?int $activeRunId = null;

    /** @var array<int, array<string, mixed>> */
    public array $facts = [];

    /** @var array<string, string> */
    public array $columns = [];

    /** @var array<int, array{label: string, value: int}> */
    public array $counts = [];

    /** @var array<int, string> */
    public array $injectionFlags = [];

    public static function getNavigationLabel(): string
    {
        return __('assistant.navigation.label');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('assistant.navigation.group');
    }

    public function getTitle(): string
    {
        return __('assistant.title');
    }

    public function mount(): void
    {
        if (blank($this->framework)) {
            $this->framework = CanonicalFramework::query()
                ->orderBy('priority')
                ->orderBy('name')
                ->value('code');
        }
    }

    public function ask(AssistantService $service): void
    {
        $this->validate([
            'question' => ['required', 'string', 'min:5', 'max:500'],
        ], attributes: [
            'question' => __('assistant.form.question'),
        ]);

        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $answer = $service->ask($user, (string) $this->question, $this->framework);

        $this->answer = $answer->answer;
        $this->notice = $answer->notice;
        $this->status = $answer->run->status->value;
        $this->intent = $answer->run->intent?->value;
        $this->activeRunId = $answer->run->getKey();
        $this->facts = $answer->rows;
        $this->columns = $answer->columns;
        $this->counts = $answer->counts;
        $this->injectionFlags = $answer->injectionFlags;
    }

    public function useSuggestion(string $question): void
    {
        $this->question = $question;
    }

    public function showRun(int $run): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $record = AiAssistantRun::query()
            ->when(! $user->isAdmin(), fn ($query) => $query->where('user_id', $user->getKey()))
            ->find($run);

        if ($record === null) {
            Notification::make()
                ->title(__('assistant.notice.run_not_found'))
                ->danger()
                ->send();

            return;
        }

        $this->question = $record->question;
        $this->answer = $record->answer;
        $this->counts = $record->counts ?? [];
        $this->status = $record->status->value;
        $this->intent = $record->intent?->value;
        $this->activeRunId = $record->getKey();
        $this->injectionFlags = $record->injection_flags ?? [];
        $this->facts = [];
        $this->columns = [];
        $this->notice = __('assistant.notice.saved_run');
        $this->framework = $record->arguments['framework'] ?? $this->framework;
    }

    /**
     * Turn an overdue-tests answer into a typed proposal (ADR 0055).
     *
     * The assistant never writes. This action creates a proposal for a human to
     * approve, bounded to five records and grounded in the run being viewed.
     */
    public function proposeTestDates(AssistantProposalService $proposals): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $run = AiAssistantRun::query()
            ->when(! $user->isAdmin(), fn (Builder $query): Builder => $query->where('user_id', $user->getKey()))
            ->find($this->activeRunId);

        if ($run === null || $run->intent !== AssistantIntent::OverdueTests || ! $user->role->canWrite()) {
            Notification::make()->title(__('assistant.propose.refused'))->danger()->send();

            return;
        }

        $implementations = Implementation::query()
            ->where(function (Builder $query): void {
                $query->whereNull('next_test_at')->orWhereDate('next_test_at', '<=', today());
            })
            ->orderBy('next_test_at')
            ->limit(AssistantOperations::MAX_OPERATIONS)
            ->get();

        if ($implementations->isEmpty()) {
            Notification::make()->title(__('assistant.propose.empty'))->warning()->send();

            return;
        }

        $operations = $implementations->map(fn (Implementation $implementation): array => [
            'op' => 'implementation.next_test_at',
            'target' => $implementation->getKey(),
            'after' => today()->addDays(90)->toDateString(),
        ])->all();

        try {
            $proposal = $proposals->propose(
                $operations,
                __('assistant.propose.summary', ['count' => count($operations)]),
                __('assistant.propose.rationale', ['run' => $run->getKey()]),
                (string) $run->getKey(),
            );
        } catch (InvalidArgumentException $exception) {
            Notification::make()
                ->title(__('assistant.propose.failed'))
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('assistant.propose.created', ['count' => count($operations)]))
            ->body(__('assistant.propose.created_body', ['reference' => '#'.$proposal->getKey()]))
            ->success()
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = auth()->user();
        $isAdmin = $user instanceof User && $user->isAdmin();

        $frameworks = CanonicalFramework::query()
            ->orderBy('priority')
            ->orderBy('name')
            ->get(['code', 'name'])
            ->mapWithKeys(fn (CanonicalFramework $framework): array => [
                (string) $framework->code => "{$framework->code} — {$framework->name}",
            ])
            ->all();

        $recentRuns = AiAssistantRun::query()
            ->with('user:id,name')
            ->when(! $isAdmin && $user instanceof User, fn ($query) => $query->where('user_id', $user->getKey()))
            ->latest()
            ->limit(10)
            ->get();

        $intents = collect(AssistantIntent::cases())
            ->map(fn (AssistantIntent $intent): array => [
                'value' => $intent->value,
                'label' => $intent->getLabel(),
                'description' => $intent->description(),
                'example' => $intent->example(),
            ])
            ->all();

        return [
            'intents' => $intents,
            'frameworks' => $frameworks,
            'recentRuns' => $recentRuns,
            'isAdmin' => $isAdmin,
            'statusLabel' => $this->status !== null
                ? AssistantRunStatus::tryFrom($this->status)?->getLabel()
                : null,
        ];
    }
}
