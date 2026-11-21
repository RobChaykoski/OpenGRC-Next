<?php

namespace App\Services\Ai\Assistant;

/**
 * The deterministic result of one catalogue query.
 *
 * Rows are already reduced to non-sensitive columns: identifiers, statuses,
 * dates and counts. An answer is never generated from a payload, a document body
 * or a person's details.
 */
final class AssistantQueryResult
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, string>  $columns  Column key => human label.
     * @param  array<int, array{label: string, value: int}>  $counts
     */
    public function __construct(
        public readonly bool $permitted,
        public readonly array $rows = [],
        public readonly array $columns = [],
        public readonly array $counts = [],
        public readonly string $context = '',
        public readonly ?string $notice = null,
    ) {}

    public static function denied(): self
    {
        return new self(
            permitted: false,
            notice: __('assistant.notice.not_permitted'),
        );
    }

    public static function needsFramework(): self
    {
        return new self(
            permitted: true,
            notice: __('assistant.notice.choose_framework'),
        );
    }
}
