<?php

namespace App\Services\Evidence;

use App\Enums\ControlStatus;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/**
 * Evaluate a validation rule against an evidence payload.
 *
 * The rule is data, not code: a list of path/operator/value assertions. A
 * missing value is Unknown, never a pass. The same payload and rule always
 * produce the same result.
 */
class ValidationRuleEvaluator
{
    /** @var array<int, string> */
    public const OPERATORS = [
        'eq',
        'ne',
        'gt',
        'gte',
        'lt',
        'lte',
        'in',
        'not_in',
        'contains',
        'exists',
        'missing',
    ];

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $payload
     * @return array{status: ControlStatus, result: array<string, mixed>}
     */
    public function evaluate(array $definition, array $payload): array
    {
        $assertions = $definition['assert'] ?? null;

        if (! is_array($assertions) || $assertions === []) {
            throw new InvalidArgumentException('A validation rule needs at least one assertion.');
        }

        $checks = [];
        $missing = [];
        $errors = [];

        foreach ($assertions as $index => $assertion) {
            if (! is_array($assertion)) {
                throw new InvalidArgumentException("Assertion [{$index}] must be an object.");
            }

            $path = $assertion['path'] ?? null;

            if (! is_string($path) || trim($path) === '') {
                throw new InvalidArgumentException("Assertion [{$index}] needs a path.");
            }

            $operator = $assertion['op'] ?? 'eq';

            if (! is_string($operator) || ! in_array($operator, self::OPERATORS, true)) {
                throw new InvalidArgumentException("Assertion [{$index}] uses an unsupported operator.");
            }

            $expected = $assertion['value'] ?? null;
            $exists = Arr::has($payload, $path);
            $actual = $exists ? data_get($payload, $path) : null;

            [$result, $error] = $this->check($operator, $exists, $actual, $expected);

            if ($result === 'unknown' && ! $exists && ! in_array($operator, ['exists', 'missing'], true)) {
                $missing[] = $path;
            }

            if ($error !== null) {
                $errors[] = "{$path}: {$error}";
            }

            $checks[] = [
                'path' => $path,
                'op' => $operator,
                'expected' => $expected,
                'actual' => $actual,
                'result' => $result,
            ];
        }

        $results = array_column($checks, 'result');

        $status = match (true) {
            in_array('failed', $results, true) => ControlStatus::Failed,
            in_array('unknown', $results, true) => ControlStatus::Unknown,
            default => ControlStatus::Effective,
        };

        return [
            'status' => $status,
            'result' => [
                'checks' => $checks,
                'missing' => array_values(array_unique($missing)),
                'errors' => $errors,
            ],
        ];
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    protected function check(string $operator, bool $exists, mixed $actual, mixed $expected): array
    {
        if ($operator === 'exists') {
            return [$exists ? 'passed' : 'failed', null];
        }

        if ($operator === 'missing') {
            return [$exists ? 'failed' : 'passed', null];
        }

        if (! $exists) {
            return ['unknown', 'value is missing'];
        }

        return match ($operator) {
            'eq' => [$this->equal($actual, $expected) ? 'passed' : 'failed', null],
            'ne' => [$this->equal($actual, $expected) ? 'failed' : 'passed', null],
            'gt', 'gte', 'lt', 'lte' => $this->compare($operator, $actual, $expected),
            'in' => is_array($expected)
                ? [in_array($actual, $expected, true) ? 'passed' : 'failed', null]
                : ['unknown', 'expected value must be an array'],
            'not_in' => is_array($expected)
                ? [in_array($actual, $expected, true) ? 'failed' : 'passed', null]
                : ['unknown', 'expected value must be an array'],
            'contains' => $this->contains($actual, $expected),
            default => ['unknown', 'unsupported operator'],
        };
    }

    protected function equal(mixed $actual, mixed $expected): bool
    {
        if (is_numeric($actual) && is_numeric($expected)) {
            return (float) $actual === (float) $expected;
        }

        return $actual === $expected;
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    protected function compare(string $operator, mixed $actual, mixed $expected): array
    {
        if (! is_numeric($actual) || ! is_numeric($expected)) {
            return ['unknown', 'comparison needs numbers'];
        }

        $a = (float) $actual;
        $b = (float) $expected;

        $passed = match ($operator) {
            'gt' => $a > $b,
            'gte' => $a >= $b,
            'lt' => $a < $b,
            'lte' => $a <= $b,
            default => false,
        };

        return [$passed ? 'passed' : 'failed', null];
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    protected function contains(mixed $actual, mixed $expected): array
    {
        if (is_array($actual)) {
            return [in_array($expected, $actual, true) ? 'passed' : 'failed', null];
        }

        if (is_string($actual) && is_scalar($expected)) {
            return [str_contains($actual, (string) $expected) ? 'passed' : 'failed', null];
        }

        return ['unknown', 'contains needs a string or array'];
    }
}
