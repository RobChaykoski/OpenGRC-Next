<?php

namespace App\Services\Ai\Assistant;

use App\Models\CanonicalFramework;

/**
 * Resolves the framework a question is about.
 *
 * An explicit framework name or code in the question wins over the page
 * selector, because the question is the more specific statement. Matching is
 * bounded so a short code like "cis" does not match the middle of a word.
 */
final class AssistantFrameworkResolver
{
    public function resolve(?string $selected, string $question): ?CanonicalFramework
    {
        $frameworks = CanonicalFramework::query()
            ->orderBy('priority')
            ->orderBy('name')
            ->get();

        if ($frameworks->isEmpty()) {
            return null;
        }

        foreach ($frameworks as $framework) {
            if ($this->mentions($question, (string) $framework->code)) {
                return $framework;
            }

            if ($this->mentions($question, (string) $framework->name)) {
                return $framework;
            }
        }

        if (filled($selected)) {
            $match = $frameworks->firstWhere('code', $selected);

            if ($match instanceof CanonicalFramework) {
                return $match;
            }
        }

        return null;
    }

    private function mentions(string $question, string $needle): bool
    {
        $needle = trim($needle);

        if (mb_strlen($needle) < 3) {
            return false;
        }

        $pattern = '/(?<![a-z0-9])'.preg_quote($needle, '/').'(?![a-z0-9])/i';

        return preg_match($pattern, $question) === 1;
    }
}
