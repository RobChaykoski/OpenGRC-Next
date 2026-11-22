<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Why a decision came out the way it did. Every decision carries exactly one
 * reason, so a reviewer can group refusals without reading the policy.
 */
enum AccessReason: string implements HasLabel
{
    case PolicyMatch = 'policy_match';
    case GrantActive = 'grant_active';
    case BreakGlass = 'break_glass';
    case NoPolicy = 'no_policy';
    case UnknownClassification = 'unknown_classification';
    case StaleClassification = 'stale_classification';
    case ContextInsufficient = 'context_insufficient';
    case ExposureThreshold = 'exposure_threshold';
    case DryRun = 'dry_run';
    case GrantExpired = 'grant_expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::PolicyMatch => 'Matched policy',
            self::GrantActive => 'Active grant',
            self::BreakGlass => 'Break-the-glass',
            self::NoPolicy => 'No matching policy',
            self::UnknownClassification => 'Classification unknown',
            self::StaleClassification => 'Classification stale',
            self::ContextInsufficient => 'Context insufficient',
            self::ExposureThreshold => 'Exposure threshold',
            self::DryRun => 'Dry-run policy',
            self::GrantExpired => 'Grant expired',
        };
    }
}
