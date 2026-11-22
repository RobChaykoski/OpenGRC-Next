<?php

namespace App\Services\Access;

use App\Enums\AccessorProfile;
use App\Enums\ContextTrust;

/**
 * What the enforcement point asserted about a request.
 *
 * The context is deliberately narrow. It carries the profile, the trust level,
 * the facility or network zone label, the purpose and the exposure weight. It
 * does not carry a raw IP address, a device identifier or clinical free text.
 */
final readonly class AccessRequestContext
{
    public function __construct(
        public AccessorProfile $profile,
        public ContextTrust $trust = ContextTrust::Low,
        public bool $hasJustification = false,
        public bool $managedDevice = false,
        public ?string $zone = null,
        public ?string $purposeCode = null,
        public bool $grantActive = false,
        public ?int $grantTtlSeconds = null,
        public int $exposureWeight = 0,
        public ?string $clinicalReason = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toSnapshot(): array
    {
        return [
            'profile' => $this->profile->value,
            'trust' => $this->trust->value,
            'managed_device' => $this->managedDevice,
            'zone' => $this->zone,
            'purpose_code' => $this->purposeCode,
            'grant_active' => $this->grantActive,
            'has_justification' => $this->hasJustification,
            'clinical_reason' => $this->clinicalReason,
            'exposure_weight' => $this->exposureWeight,
        ];
    }
}
