<?php

namespace App\Services\Dlp;

/**
 * The result of scanning a piece of content.
 */
final readonly class DlpScanResult
{
    /**
     * @param  array<int, DlpMatch>  $matches
     */
    public function __construct(
        public array $matches = [],
        public string $scanner = 'regex',
    ) {}

    public function isEmpty(): bool
    {
        return $this->matches === [];
    }

    /**
     * @return array<int, string>
     */
    public function types(): array
    {
        return array_values(array_unique(array_map(
            fn (DlpMatch $match): string => $match->type,
            $this->matches,
        )));
    }

    /**
     * @return array<int, string>
     */
    public function labels(): array
    {
        return array_values(array_unique(array_map(
            fn (DlpMatch $match): string => $match->label,
            $this->matches,
        )));
    }

    /**
     * @param  array<int, string>  $types
     */
    public function hasAny(array $types): bool
    {
        return array_intersect($this->types(), $types) !== [];
    }

    public function highestSeverity(): string
    {
        $rank = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
        $highest = 'low';

        foreach ($this->matches as $match) {
            if (($rank[$match->severity] ?? 0) > ($rank[$highest] ?? 0)) {
                $highest = $match->severity;
            }
        }

        return $highest;
    }

    public function summary(): string
    {
        if ($this->isEmpty()) {
            return 'No sensitive content detected.';
        }

        $parts = [];

        foreach ($this->matches as $match) {
            $parts[] = $match->label.($match->count > 1 ? " ({$match->count})" : '');
        }

        return implode(', ', $parts);
    }
}
