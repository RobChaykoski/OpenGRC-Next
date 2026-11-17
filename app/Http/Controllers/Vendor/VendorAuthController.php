<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Survey;
use App\Models\VendorUser;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VendorAuthController extends Controller
{
    public function magicLogin(Request $request, VendorUser $vendorUser)
    {
        // Validate signed URL
        if (! $request->hasValidSignature()) {
            abort(401, 'This link has expired or is invalid.');
        }

        // Check if vendor user is not deleted
        if ($vendorUser->trashed()) {
            abort(403, 'This account has been deactivated.');
        }

        // Log the user in
        Auth::guard('vendor')->login($vendorUser);

        // Update last login
        $vendorUser->update(['last_login_at' => now()]);

        // Regenerate session
        session()->regenerate();

        // If user has no password, redirect to set password page
        if (! $vendorUser->hasPassword()) {
            return redirect()->route('filament.vendor.pages.set-password');
        }

        // Otherwise, redirect to dashboard
        return redirect()->intended(Filament::getPanel('vendor')->getUrl());
    }

    /**
     * Magic link to access a specific survey.
     *
     * The signed URL proves the link was delivered to the respondent's email.
     * That is the only identity claim accepted here: the page is never opened
     * from an unsigned query string, and self-registration is gone.
     */
    public function surveyMagicLink(Request $request, Survey $survey)
    {
        // Validate signed URL
        if (! $request->hasValidSignature()) {
            abort(401, 'This link has expired or is invalid.');
        }

        // Check survey exists and has a vendor
        if (! $survey->vendor_id) {
            abort(404, 'Survey not found.');
        }

        $vendorUser = Auth::guard('vendor')->user();

        // A signed-in vendor user from another vendor must not see this survey.
        if ($vendorUser && $vendorUser->vendor_id !== $survey->vendor_id) {
            Auth::guard('vendor')->logout();
            $vendorUser = null;
        }

        if (! $vendorUser) {
            $email = $survey->respondent_email;

            if (! $email) {
                abort(404, 'This survey has no respondent.');
            }

            $vendorUser = VendorUser::withTrashed()->where('email', $email)->first();

            if ($vendorUser && ($vendorUser->trashed() || $vendorUser->vendor_id !== $survey->vendor_id)) {
                abort(403, 'This survey is not available for this account.');
            }

            if (! $vendorUser) {
                $vendorUser = VendorUser::create([
                    'vendor_id' => $survey->vendor_id,
                    'name' => $survey->respondent_name ?: $email,
                    'email' => $email,
                    'email_verified_at' => now(),
                ]);
            }

            Auth::guard('vendor')->login($vendorUser);
            $vendorUser->update(['last_login_at' => now()]);
            session()->regenerate();
        }

        // Passwordless accounts must set a password before using the portal.
        if (! $vendorUser->hasPassword()) {
            return redirect()->route('filament.vendor.pages.set-password');
        }

        return redirect()->route('filament.vendor.resources.surveys.respond', ['record' => $survey->id]);
    }
}
