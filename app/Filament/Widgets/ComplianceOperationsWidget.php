<?php

namespace App\Filament\Widgets;

use App\Enums\AccessReviewDecision as DecisionType;
use App\Enums\AccessReviewStatus;
use App\Enums\NonHumanIdentityStatus;
use App\Enums\PolicyExceptionStatus;
use App\Enums\PrivacyRequestStatus;
use App\Filament\Pages\MyPolicies;
use App\Filament\Resources\AccessReviewResource;
use App\Filament\Resources\NonHumanIdentityResource;
use App\Filament\Resources\PolicyExceptionResource;
use App\Filament\Resources\PrivacyRequestResource;
use App\Filament\Resources\RiskResource;
use App\Filament\Resources\SbomDocumentResource;
use App\Models\AccessReview;
use App\Models\AccessReviewDecision as AccessDecision;
use App\Models\NonHumanIdentity;
use App\Models\Policy;
use App\Models\PolicyAcknowledgement;
use App\Models\PolicyException;
use App\Models\PrivacyRequest;
use App\Models\Risk;
use App\Models\SoftwareVulnerability;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Operational queue across the compliance registers.
 *
 * The widget shows counts and links; it does not duplicate the register pages or
 * make a compliance decision.
 */
class ComplianceOperationsWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $user = auth()->user();

        $policyOutstanding = 0;

        if ($user !== null) {
            $policies = Policy::query()
                ->where(function ($query): void {
                    $query->whereNull('retired_date')->orWhere('retired_date', '>=', today());
                })
                ->where(function ($query): void {
                    $query->whereNull('effective_date')->orWhere('effective_date', '<=', today());
                })
                ->get();

            $acknowledgements = PolicyAcknowledgement::query()
                ->where('user_id', $user->getKey())
                ->orderByDesc('acknowledged_at')
                ->get()
                ->unique('policy_id')
                ->keyBy('policy_id');

            $policyOutstanding = $policies->filter(function (Policy $policy) use ($acknowledgements): bool {
                $acknowledgement = $acknowledgements->get($policy->getKey());

                return $acknowledgement === null
                    || $acknowledgement->policy_revision !== $policy->currentRevision();
            })->count();
        }

        $openAdvisories = SoftwareVulnerability::query()
            ->where('status', 'open')
            ->count();

        $activeReviews = AccessReview::query()
            ->where('status', AccessReviewStatus::Active->value)
            ->count();

        $pendingDecisions = AccessDecision::query()
            ->where('decision', DecisionType::Pending->value)
            ->whereHas('review', fn ($query) => $query->where('status', AccessReviewStatus::Active->value))
            ->count();

        $identityIssues = NonHumanIdentity::query()
            ->whereIn('status', [
                NonHumanIdentityStatus::RotationDue->value,
                NonHumanIdentityStatus::Expired->value,
            ])
            ->count();

        $acceptanceExpiring = Risk::query()->acceptanceExpiring()->count();
        $exceptionsExpiring = PolicyException::query()
            ->where('status', PolicyExceptionStatus::Approved->value)
            ->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '>=', today())
            ->whereDate('expiration_date', '<=', today()->addDays(30))
            ->count();

        $privacyOverdue = PrivacyRequest::query()
            ->where('status', PrivacyRequestStatus::Overdue->value)
            ->count();

        return [
            Stat::make('Policies to acknowledge', $policyOutstanding)
                ->description('Assigned to you')
                ->descriptionIcon('heroicon-m-document-check')
                ->color($policyOutstanding > 0 ? 'warning' : 'success')
                ->url(MyPolicies::getUrl()),

            Stat::make('Open SBOM advisories', $openAdvisories)
                ->description('Vulnerable components')
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color($openAdvisories > 0 ? 'danger' : 'success')
                ->url(SbomDocumentResource::getUrl()),

            Stat::make('Access reviews', $activeReviews)
                ->description($pendingDecisions.' pending decision(s)')
                ->descriptionIcon('heroicon-m-user-group')
                ->color($activeReviews > 0 ? 'warning' : 'gray')
                ->url(AccessReviewResource::getUrl()),

            Stat::make('Machine credentials', $identityIssues)
                ->description('Rotation due or expired')
                ->descriptionIcon('heroicon-m-key')
                ->color($identityIssues > 0 ? 'danger' : 'success')
                ->url(NonHumanIdentityResource::getUrl()),

            Stat::make('Acceptances expiring', $acceptanceExpiring)
                ->description('Within 30 days')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color($acceptanceExpiring > 0 ? 'warning' : 'gray')
                ->url(RiskResource::getUrl()),

            Stat::make('Exceptions expiring', $exceptionsExpiring)
                ->description('Within 30 days')
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color($exceptionsExpiring > 0 ? 'warning' : 'gray')
                ->url(PolicyExceptionResource::getUrl()),

            Stat::make('Privacy requests overdue', $privacyOverdue)
                ->description('Past the statutory clock')
                ->descriptionIcon('heroicon-m-clock')
                ->color($privacyOverdue > 0 ? 'danger' : 'success')
                ->url(PrivacyRequestResource::getUrl()),
        ];
    }
}
