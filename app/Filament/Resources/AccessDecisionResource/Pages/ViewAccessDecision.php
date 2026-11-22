<?php

namespace App\Filament\Resources\AccessDecisionResource\Pages;

use App\Filament\Resources\AccessDecisionResource;
use Filament\Resources\Pages\ViewRecord;

/**
 * No header actions on purpose: a decision is evidence, not an editable record.
 */
class ViewAccessDecision extends ViewRecord
{
    protected static string $resource = AccessDecisionResource::class;
}
