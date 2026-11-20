<?php

namespace App\Services\Controls;

use App\Enums\CrosswalkStatus;
use App\Models\Control;
use App\Models\ControlCrosswalk;
use App\Models\User;
use InvalidArgumentException;

/**
 * Create and review control crosswalks.
 *
 * A crosswalk must connect two different controls from two different standards.
 * The same pair cannot be recorded twice, in either direction.
 */
class ControlCrosswalkService
{
    /**
     * @param  array{source_control_id: int, target_control_id: int, rationale: string, confidence: string}  $data
     */
    public function create(array $data, User $creator): ControlCrosswalk
    {
        $source = Control::query()->find($data['source_control_id']);
        $target = Control::query()->find($data['target_control_id']);

        if (! $source instanceof Control || ! $target instanceof Control) {
            throw new InvalidArgumentException('Both controls must exist.');
        }

        $this->assertPair($source, $target);

        return ControlCrosswalk::query()->create([
            'source_control_id' => $source->getKey(),
            'target_control_id' => $target->getKey(),
            'rationale' => $data['rationale'],
            'confidence' => $data['confidence'],
            'status' => CrosswalkStatus::Draft,
            'created_by' => $creator->getKey(),
        ]);
    }

    public function approve(ControlCrosswalk $crosswalk, User $reviewer, ?string $note = null): ControlCrosswalk
    {
        $this->assertDraft($crosswalk);

        $crosswalk->update([
            'status' => CrosswalkStatus::Approved,
            'review_note' => $note,
            'reviewed_by' => $reviewer->getKey(),
            'reviewed_at' => now(),
        ]);

        return $crosswalk->refresh();
    }

    public function reject(ControlCrosswalk $crosswalk, User $reviewer, string $note): ControlCrosswalk
    {
        $this->assertDraft($crosswalk);

        $crosswalk->update([
            'status' => CrosswalkStatus::Rejected,
            'review_note' => $note,
            'reviewed_by' => $reviewer->getKey(),
            'reviewed_at' => now(),
        ]);

        return $crosswalk->refresh();
    }

    public function assertPair(Control $source, Control $target, ?int $ignoreId = null): void
    {
        if ($source->is($target)) {
            throw new InvalidArgumentException('A control cannot be crosswalked to itself.');
        }

        if ((int) $source->standard_id === (int) $target->standard_id) {
            throw new InvalidArgumentException('Crosswalks must connect controls from different standards.');
        }

        $exists = ControlCrosswalk::query()
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->where(function ($query) use ($source, $target): void {
                $query->where('source_control_id', $source->getKey())
                    ->where('target_control_id', $target->getKey());
            })
            ->orWhere(function ($query) use ($source, $target, $ignoreId): void {
                $query->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
                    ->where('source_control_id', $target->getKey())
                    ->where('target_control_id', $source->getKey());
            })
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException('A crosswalk for this control pair already exists.');
        }
    }

    protected function assertDraft(ControlCrosswalk $crosswalk): void
    {
        if ($crosswalk->status !== CrosswalkStatus::Draft) {
            throw new InvalidArgumentException('Only a draft crosswalk can be reviewed.');
        }
    }
}
