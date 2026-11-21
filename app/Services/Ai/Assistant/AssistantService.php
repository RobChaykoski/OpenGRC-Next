<?php

namespace App\Services\Ai\Assistant;

use App\Enums\AssistantIntent;
use App\Enums\AssistantRunStatus;
use App\Enums\QuotaType;
use App\Exceptions\DlpBlockedException;
use App\Exceptions\QuotaExceededException;
use App\Models\AiAssistantRun;
use App\Models\User;
use App\Services\Ai\AiService;
use App\Services\AppLogger;
use App\Services\QuotaService;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Read-only question flow: route, retrieve, narrate, record (ADR 0046).
 *
 * The model never chooses a query and never writes. It receives a bounded fact
 * set and returns the interpretation; the facts stay on screen next to it.
 */
final class AssistantService
{
    public function __construct(
        private readonly AssistantQuestionRouter $router,
        private readonly AssistantFrameworkResolver $frameworks,
        private readonly AssistantQueryRunner $queries,
        private readonly AssistantPrompt $prompt,
        private readonly PromptGuard $guard,
        private readonly AiService $ai,
    ) {}

    public function ask(User $user, string $question, ?string $frameworkCode = null): AssistantAnswer
    {
        $question = trim($question);
        $intent = $this->router->route($question);
        $framework = $intent?->requiresFramework()
            ? $this->frameworks->resolve($frameworkCode, $question)
            : null;

        $arguments = $framework !== null ? ['framework' => $framework->code] : [];

        if ($intent === null) {
            $result = new AssistantQueryResult(permitted: true);
            $notice = __('assistant.notice.unsupported');

            return $this->store(
                user: $user,
                question: $question,
                intent: null,
                arguments: [],
                status: AssistantRunStatus::Unsupported,
                result: $result,
                answer: null,
                flags: $this->guard->flags($question),
                notice: $notice,
            );
        }

        $result = $this->queries->run($intent, $framework);

        if (! $result->permitted) {
            return $this->store(
                user: $user,
                question: $question,
                intent: $intent,
                arguments: $arguments,
                status: AssistantRunStatus::NotPermitted,
                result: $result,
                answer: null,
                flags: $this->guard->flags($question),
                notice: $result->notice,
            );
        }

        if ($intent->requiresFramework() && $framework === null) {
            return $this->store(
                user: $user,
                question: $question,
                intent: $intent,
                arguments: $arguments,
                status: AssistantRunStatus::NeedsInput,
                result: $result,
                answer: null,
                flags: $this->guard->flags($question),
                notice: $result->notice,
            );
        }

        $facts = json_encode(
            ['counts' => $result->counts, 'rows' => $result->rows],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) ?: '';

        $flags = $this->guard->flags($question, $facts);

        if (! $this->ai->isConfigured()) {
            return $this->store(
                user: $user,
                question: $question,
                intent: $intent,
                arguments: $arguments,
                status: AssistantRunStatus::FactsOnly,
                result: $result,
                answer: null,
                flags: $flags,
                notice: __('assistant.notice.not_configured'),
            );
        }

        try {
            QuotaService::check(QuotaType::AI_PROMPT);
            QuotaService::check(QuotaType::AI_RESPONSE);
        } catch (QuotaExceededException $exception) {
            $this->logQuota($exception, $intent);

            return $this->store(
                user: $user,
                question: $question,
                intent: $intent,
                arguments: $arguments,
                status: AssistantRunStatus::QuotaExceeded,
                result: $result,
                answer: null,
                flags: $flags,
                notice: __('assistant.notice.quota_exceeded'),
            );
        }

        try {
            $response = $this->ai->chatCompletion(
                $this->prompt->system(),
                $this->prompt->user($question, $result->counts, $result->rows, $result->context),
            );
        } catch (DlpBlockedException $exception) {
            AppLogger::warning(
                category: 'ai_assistant',
                event: 'DlpBlocked',
                message: 'Assistant prompt blocked by data loss prevention',
                context: [
                    'intent' => $intent->value,
                    'match_types' => array_map(fn ($match) => $match->type, $exception->result->matches),
                ],
                causer: $user,
            );

            return $this->store(
                user: $user,
                question: $question,
                intent: $intent,
                arguments: $arguments,
                status: AssistantRunStatus::BlockedDlp,
                result: $result,
                answer: null,
                flags: $flags,
                notice: __('assistant.notice.dlp_blocked'),
            );
        } catch (QuotaExceededException $exception) {
            $this->logQuota($exception, $intent);

            return $this->store(
                user: $user,
                question: $question,
                intent: $intent,
                arguments: $arguments,
                status: AssistantRunStatus::QuotaExceeded,
                result: $result,
                answer: null,
                flags: $flags,
                notice: __('assistant.notice.quota_exceeded'),
            );
        } catch (Throwable $exception) {
            Log::warning('Assistant AI call failed.', [
                'intent' => $intent->value,
                'exception' => $exception::class,
            ]);

            return $this->store(
                user: $user,
                question: $question,
                intent: $intent,
                arguments: $arguments,
                status: AssistantRunStatus::Error,
                result: $result,
                answer: null,
                flags: $flags,
                notice: __('assistant.notice.error'),
                error: $exception::class,
            );
        }

        if (isset($response['usage']['prompt_tokens'])) {
            QuotaService::record(QuotaType::AI_PROMPT, (int) $response['usage']['prompt_tokens']);
        }

        if (isset($response['usage']['completion_tokens'])) {
            QuotaService::record(QuotaType::AI_RESPONSE, (int) $response['usage']['completion_tokens']);
        }

        $answer = $this->cleanAnswer((string) $response['content']);

        return $this->store(
            user: $user,
            question: $question,
            intent: $intent,
            arguments: $arguments,
            status: AssistantRunStatus::Answered,
            result: $result,
            answer: $answer,
            flags: $flags,
            notice: $result->notice,
            meta: [
                'provider' => $this->ai->getProvider()->value,
                'model' => $response['model'] ?? $this->ai->getModel(),
                'prompt_tokens' => $response['usage']['prompt_tokens'] ?? null,
                'completion_tokens' => $response['usage']['completion_tokens'] ?? null,
            ],
        );
    }

    private function cleanAnswer(string $answer): string
    {
        $answer = strip_tags($answer);
        $answer = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $answer) ?? $answer;
        $answer = trim($answer);

        return mb_substr($answer, 0, 4000);
    }

    private function logQuota(QuotaExceededException $exception, AssistantIntent $intent): void
    {
        AppLogger::warning(
            category: 'ai_assistant',
            event: 'QuotaExceeded',
            message: 'Assistant request blocked due to quota',
            context: [
                'intent' => $intent->value,
                'quota_type' => $exception->getQuotaType()->value,
                'usage' => $exception->getCurrentUsage(),
                'limit' => $exception->getLimit(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @param  array<int, string>  $flags
     * @param  array<string, mixed>  $meta
     */
    private function store(
        User $user,
        string $question,
        ?AssistantIntent $intent,
        array $arguments,
        AssistantRunStatus $status,
        AssistantQueryResult $result,
        ?string $answer,
        array $flags,
        ?string $notice,
        array $meta = [],
        ?string $error = null,
    ): AssistantAnswer {
        $run = AiAssistantRun::create([
            'user_id' => $user->getKey(),
            'question' => $status === AssistantRunStatus::BlockedDlp
                ? __('assistant.redacted_question')
                : mb_substr($question, 0, 500),
            'intent' => $intent,
            'arguments' => $arguments === [] ? null : $arguments,
            'status' => $status,
            'fact_count' => count($result->rows),
            'counts' => $result->counts,
            'result_sha256' => hash('sha256', (string) json_encode(
                ['counts' => $result->counts, 'rows' => $result->rows],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            )),
            'answer' => $answer,
            'provider' => $meta['provider'] ?? null,
            'model' => isset($meta['model']) ? mb_substr((string) $meta['model'], 0, 96) : null,
            'prompt_tokens' => $meta['prompt_tokens'] ?? null,
            'completion_tokens' => $meta['completion_tokens'] ?? null,
            'injection_flags' => $flags === [] ? null : $flags,
            'notice' => $notice !== null ? mb_substr($notice, 0, 500) : null,
            'error' => $error !== null ? mb_substr($error, 0, 500) : null,
        ]);

        AppLogger::info(
            category: 'ai_assistant',
            event: 'Run',
            message: 'Read-only assistant run recorded',
            context: [
                'run_id' => $run->getKey(),
                'intent' => $intent?->value,
                'status' => $status->value,
                'fact_count' => $run->fact_count,
                'result_sha256' => $run->result_sha256,
                'injection_flags' => $run->injection_flags,
            ],
            subject: $run,
            causer: $user,
        );

        return new AssistantAnswer(
            run: $run,
            intent: $intent,
            rows: $result->rows,
            columns: $result->columns,
            counts: $result->counts,
            answer: $answer,
            notice: $notice,
            injectionFlags: $flags,
        );
    }
}
