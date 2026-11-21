<?php

namespace App\Filament\Admin\Resources\AccessRuleResource\Pages;

use App\Filament\Admin\Resources\AccessRuleResource;
use App\Models\AccessRule;
use App\Services\Access\AccessRuleLockoutGuard;
use App\Support\Access\AccessRuleValue;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateAccessRule extends CreateRecord
{
    protected static string $resource = AccessRuleResource::class;

    protected function beforeCreate(): void
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
            ->exists()) {
            throw ValidationException::withMessages([
                'data.value' => __('access-rules.form.duplicate'),
            ]);
        }

        $candidate = $this->candidate($this->data);

        if (app(AccessRuleLockoutGuard::class)->wouldLockOutCurrentUser($candidate)) {
            throw ValidationException::withMessages([
                'data.effect' => __('access-rules.lockout'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function candidate(array $data): AccessRule
    {
        $candidate = new AccessRule;
        $candidate->forceFill([
            'type' => $data['type'] ?? null,
            'effect' => $data['effect'] ?? null,
            'scope' => $data['scope'] ?? null,
            'value' => $data['value'] ?? '',
            'is_active' => (bool) ($data['is_active'] ?? false),
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        return $candidate;
    }
}
