<?php

namespace App\Services\Evidence;

use App\Enums\ControlStatus;
use App\Models\ControlValidation;

/**
 * Counters for one validation run. The command prints these.
 */
final class ValidationRun
{
    public int $validations = 0;

    public int $effective = 0;

    public int $failed = 0;

    public int $unknown = 0;

    public int $findingsOpened = 0;

    public int $findingsUpdated = 0;

    public int $findingsResolved = 0;

    public function record(ControlValidation $validation): void
    {
        $this->validations++;

        match ($validation->status) {
            ControlStatus::Effective => $this->effective++,
            ControlStatus::Failed => $this->failed++,
            ControlStatus::Unknown => $this->unknown++,
        };
    }
}
