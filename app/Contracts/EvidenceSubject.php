<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

interface EvidenceSubject
{
    public function evidencePathColumn(): string;

    public function evidencePath(): ?string;

    public function evidenceDisk(): string;

    public function ledgerEntries(): MorphMany;

    public function evidenceState(): MorphOne;
}
