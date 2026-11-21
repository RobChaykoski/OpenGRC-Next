<?php

namespace App\Filament\Admin\Resources\AccessRuleResource\Pages;

use App\Filament\Admin\Resources\AccessRuleResource;
use App\Models\AccessRule;
use App\Services\Access\AccessRuleLockoutGuard;
use App\Support\Access\AccessRuleValue;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditAccessRule extends EditRecord
{
    protected static string $resource = AccessRuleResource::class;

    protected function beforeSave(): void
    {
        if (! AccessRuleValue::isValid($this->data['type'] ?? '', $this->data['value'] ?? '')) {
            throw ValidationException::withMessages([
                'data.value' => __('access-rules.form.value_invalid'),
            ]);
        }

        $value = AccessRuleValue::normalize($this->data['type'], $this->data['value']);

        if (AccessRule::query()
            ->where('type', $this->data['type'])
            ->where('effect', $this->data['effect'])
            ->where('scope', $this->data['scope'])
            ->where('value', $value)
            ->whereKeyNot($this->record->getKey())
            ->exists()) {
            throw ValidationException::withMessages([
                'data.value' => __('access-rules.form.duplicate'),
            ]);
        }

        $candidate = new AccessRule;
        $candidate->forceFill([
            'type' => $this->data['type'] ?? null,
            'effect' => $this->data['effect'] ?? null,
            'scope' => $this->data['scope'] ?? null,
            'value' => $this->data['value'] ?? '',
            'is_active' => (bool) ($this->data['is_active'] ?? false),
            'expires_at' => $this->data['expires_at'] ?? null,
        ]);

        if (app(AccessRuleLockoutGuard::class)->wouldLockOutCurrentUser($candidate, $this->record->getKey())) {
            throw ValidationException::withMessages([
                'data.effect' => __('access-rules.lockout'),
            ]);
        }
    }
}
