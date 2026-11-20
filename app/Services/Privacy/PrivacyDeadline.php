<?php

namespace App\Services\Privacy;

use App\Enums\PrivacyRegime;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Statutory response clocks.
 *
 * These are working defaults. Counsel confirms the exact period, the calendar
 * method and the extension rules for each jurisdiction and matter.
 */
class PrivacyDeadline
{
    public static function dueAt(PrivacyRegime $regime, CarbonInterface $receivedAt): CarbonImmutable
    {
        $received = CarbonImmutable::parse($receivedAt)->startOfDay();

        return match ($regime) {
            PrivacyRegime::Gdpr => $received->addMonthNoOverflow(),
            default => $received->addDays($regime->responseDays()),
        };
    }

    public static function extendedDueAt(PrivacyRegime $regime, CarbonInterface $receivedAt): CarbonImmutable
    {
        $received = CarbonImmutable::parse($receivedAt)->startOfDay();

        return match ($regime) {
            PrivacyRegime::Gdpr => $received->addMonthsNoOverflow(3),
            default => $received->addDays($regime->responseDays() + $regime->extensionDays()),
        };
    }
}
