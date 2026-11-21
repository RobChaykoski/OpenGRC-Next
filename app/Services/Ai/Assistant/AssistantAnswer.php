<?php

namespace App\Services\Ai\Assistant;

use App\Enums\AssistantIntent;
use App\Models\AiAssistantRun;

/**
 * What the page receives after one question.
 *
 * Facts are returned for display even when the narrative is missing. The user
 * can always check the deterministic result against the AI interpretation.
 */
final class AssistantAnswer
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, string>  $columns
     * @param  array<int, array{label: string, value: int}>  $counts
     * @param  array<int, string>  $injectionFlags
     */
    public function __construct(
        public readonly AiAssistantRun $run,
        public readonly ?AssistantIntent $intent,
        public readonly array $rows,
        public readonly array $columns,
        public readonly array $counts,
        public readonly ?string $answer,
        public readonly ?string $notice,
        public readonly array $injectionFlags,
    ) {}
}
