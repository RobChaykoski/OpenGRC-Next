<?php

namespace App\Services\Assistant;

use App\Enums\ImplementationStatus;
use App\Models\Implementation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Throwable;

/**
 * The only operations an assistant proposal may contain (ADR 0055).
 *
 * This class is the security boundary: the model proposes an operation by name,
 * and only names in this catalogue are understood. Append-only and
 * security-sensitive records are absent by construction, not by a prompt rule.
 */
class AssistantOperations
{
    /**
     * @var array<string, array{model: class-string<Model>, field: string, cast: string}>
     */
    public const TYPES = [
        'implementation.status' => [
            'model' => Implementation::class,
            'field' => 'status',
            'cast' => ImplementationStatus::class,
        ],
        'implementation.next_test_at' => [
            'model' => Implementation::class,
            'field' => 'next_test_at',
            'cast' => 'date',
        ],
    ];

    public const MAX_OPERATIONS = 5;

    /**
     * Validate the shape and the after-value, and capture the current value as
     * `before`. A caller-supplied `before` is ignored: the record decides.
     *
     * @param  array<string, mixed>  $operation
     * @return array{op: string, target: int, before: string|null, after: string|null}
     */
    public function normalise(array $operation): array
    {
        $name = $operation['op'] ?? null;

        if (! is_string($name) || ! array_key_exists($name, self::TYPES)) {
            throw new InvalidArgumentException('Unsupported assistant operation.');
        }

        $definition = self::TYPES[$name];
        $model = $this->target($name, $operation['target'] ?? null);

        return [
            'op' => $name,
            'target' => (int) $model->getKey(),
            'before' => $this->canonical($model, $definition),
            'after' => $this->normaliseValue($definition, $operation['after'] ?? null),
        ];
    }

    /**
     * Apply one normalised operation, refusing if the record has moved since the
     * proposal was made.
     *
     * @param  array{op: string, target: int, before: string|null, after: string|null}  $operation
     */
    public function apply(array $operation): void
    {
        $definition = self::TYPES[$operation['op']];
        $model = $this->target($operation['op'], $operation['target']);

        if ($this->canonical($model, $definition) !== $operation['before']) {
            throw new InvalidArgumentException('The record changed after the proposal was made.');
        }

        $model->setAttribute($definition['field'], $operation['after']);
        $model->save();
    }

    /**
     * @param  array{op: string, target: int, before: string|null, after: string|null}  $operation
     * @return array{op: string, target: int, before: string|null, after: string|null}
     */
    public function inverse(array $operation): array
    {
        return [
            'op' => $operation['op'],
            'target' => $operation['target'],
            'before' => $operation['after'],
            'after' => $operation['before'],
        ];
    }

    protected function target(string $name, mixed $target): Model
    {
        $definition = self::TYPES[$name];
        $model = $definition['model']::query()->find(is_numeric($target) ? (int) $target : 0);

        if (! $model instanceof Model) {
            throw new InvalidArgumentException('The proposal target does not exist.');
        }

        return $model;
    }

    /**
     * @param  array{model: class-string<Model>, field: string, cast: string}  $definition
     */
    protected function canonical(Model $model, array $definition): ?string
    {
        $value = $model->getAttribute($definition['field']);

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)->toDateString();
        }

        return $value === null ? null : (string) $value;
    }

    /**
     * @param  array{model: class-string<Model>, field: string, cast: string}  $definition
     */
    protected function normaliseValue(array $definition, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $cast = $definition['cast'];

        if ($cast === 'date') {
            try {
                return CarbonImmutable::parse((string) $value)->toDateString();
            } catch (Throwable) {
                throw new InvalidArgumentException('The proposed date is not a date.');
            }
        }

        /** @var class-string<\BackedEnum> $cast */
        $enum = $cast::tryFrom((string) $value);

        if ($enum === null) {
            throw new InvalidArgumentException('The proposed value is not valid for this field.');
        }

        return (string) $enum->value;
    }
}
