<?php

namespace App\Services\Ai\Assistant;

/**
 * Conservative flags for prompt-injection attempts.
 *
 * A flag never blocks a question. It is recorded on the run so a reviewer can
 * see that the input tried to change the model's instructions, and the prompt
 * states plainly that retrieved data is not instruction.
 */
final class PromptGuard
{
    /** @var array<string, string> */
    private const PATTERNS = [
        'ignore_previous' => '/ignore\s+(all\s+|any\s+)?(the\s+)?(previous|prior|above)\s+(instructions|prompts?|rules)/i',
        'disregard_instructions' => '/disregard\s+(all\s+|any\s+)?(the\s+)?(previous|prior|above|system)/i',
        'system_prompt' => '/\bsystem\s+(prompt|message)\b/i',
        'role_override' => '/\byou\s+are\s+now\b/i',
        'reveal_prompt' => '/\b(reveal|print|show|repeat)\s+(the\s+)?(system|developer|hidden)\s+(prompt|message|instructions)/i',
        'tool_override' => '/\b(call|invoke|run)\s+(the\s+)?(tool|function|query)\b/i',
    ];

    /**
     * @return array<int, string>
     */
    public function flags(string ...$texts): array
    {
        $flags = [];

        foreach ($texts as $text) {
            foreach (self::PATTERNS as $flag => $pattern) {
                if (preg_match($pattern, $text) === 1) {
                    $flags[$flag] = true;
                }
            }
        }

        return array_keys($flags);
    }
}
