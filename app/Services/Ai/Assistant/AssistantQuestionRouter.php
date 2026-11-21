<?php

namespace App\Services\Ai\Assistant;

use App\Enums\AssistantIntent;

/**
 * Maps a question to one reviewed query, deterministically.
 *
 * The router is deliberately simple and boring. Letting a model choose tools
 * would make retrieved content part of the control path, which is exactly the
 * prompt-injection failure mode ADR 0046 rejects. A phrase with no match gets a
 * list of supported questions instead of an invented SQL query.
 */
final class AssistantQuestionRouter
{
    public function route(string $question): ?AssistantIntent
    {
        $normalised = $this->normalise($question);

        if ($normalised === '') {
            return null;
        }

        $best = null;
        $bestScore = 0;
        $ties = 0;

        foreach (AssistantIntent::cases() as $intent) {
            $score = $this->score($normalised, $intent->keywords());

            if ($score > $bestScore) {
                $best = $intent;
                $bestScore = $score;
                $ties = 1;
            } elseif ($score === $bestScore && $score > 0) {
                $ties++;
            }
        }

        if ($best === null) {
            return null;
        }

        // Equal evidence for two intents is ambiguity, not a guess. The page
        // shows the catalogue so the user can rephrase.
        if ($ties > 1) {
            return null;
        }

        return $best;
    }

    private function normalise(string $question): string
    {
        $question = mb_strtolower(trim($question));
        $question = preg_replace('/\s+/', ' ', $question) ?? $question;

        return trim($question, " \t\n\r\0\x0B?.!,;:\"'");
    }

    /**
     * Weight longer phrases more: "overdue test" is stronger evidence than "test".
     *
     * @param  array<int, string>  $keywords
     */
    private function score(string $question, array $keywords): int
    {
        $score = 0;

        foreach ($keywords as $keyword) {
            $keyword = mb_strtolower($keyword);

            if ($keyword !== '' && str_contains($question, $keyword)) {
                $score += max(1, mb_strlen($keyword));
            }
        }

        return $score;
    }
}
