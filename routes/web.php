<?php

use App\Http\Controllers\AttachmentDownloadController;
use App\Http\Controllers\AuditPortalController;
use App\Http\Controllers\DocumentTemplateDownloadController;
use App\Http\Controllers\MediaProxyController;
use App\Http\Controllers\PrivateStorageController;
use App\Http\Controllers\SurveyAttachmentController;
use App\Http\Controllers\TrustCenterController;
use App\Http\Controllers\Vendor\VendorAuthController;
use App\Http\Controllers\Vendor\VendorDocumentController;
use App\Livewire\PasswordResetPage;
use App\Models\Survey;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;

// Health check endpoint for load balancers and Docker health checks
Route::get('/health', function () {
    return response()->json(['status' => 'ok'], 200);
});

Route::get('/', function () {
    return redirect()->route('filament.app.auth.login');
});

// override default login route to point to Filament login
Route::get('login', function () {
    return redirect()->route('filament.app.auth.login');
})->name('login');

// The admin panel shares the app panel's login page. Redirect the conventional
// admin login URL rather than leaving a 404 for anyone who bookmarks it.
Route::get('admin/login', function () {
    return redirect()->route('filament.app.auth.login');
});

Route::middleware(['auth'])->group(function () {

    Route::get('/app/reset-password', PasswordResetPage::class)->name('password-reset-page');

    Route::get('/app/priv-storage/{filepath}', PrivateStorageController::class)
        ->where('filepath', '.*')
        ->name('priv-storage');

    // Signed evidence download. URLs are short-lived and bound to the file.
    Route::get('/attachments/{fileAttachment}/download', AttachmentDownloadController::class)
        ->middleware('signed')
        ->name('attachments.download');

    // Document template original file. Authorization is the template policy.
    Route::get('/app/document-templates/{template}/download', DocumentTemplateDownloadController::class)
        ->name('document-templates.download');

    // Media proxy route for serving private S3/cloud storage files
    Route::get('/media/{path}', [MediaProxyController::class, 'show'])
        ->where('path', '.*')
        ->name('media.show');

    // Survey attachment download route
    Route::get('/survey-attachment/{attachment}/download', [SurveyAttachmentController::class, 'download'])
        ->name('survey-attachment.download');

});

// Add Socialite routes
Route::get('auth/{provider}/redirect', '\App\Http\Controllers\Auth\AuthController@redirectToProvider')->name('socialite.redirect');
Route::get('auth/{provider}/callback', '\App\Http\Controllers\Auth\AuthController@handleProviderCallback')->name('socialite.callback');

// Legacy public survey routes - redirect to new magic link URL
// (Surveys are now handled through the authenticated Vendor Panel with magic links)
Route::get('survey/{token}', function ($token) {
    // Find the survey by access token
    $survey = Survey::where('access_token', $token)->first();

    if (! $survey) {
        abort(404, 'Survey not found');
    }

    // Redirect to the new magic link URL
    return redirect($survey->getPublicUrl());
})->name('survey.show');

// Vendor Portal Magic Link Routes
Route::get('/portal/auth/magic-login/{vendorUser}', [VendorAuthController::class, 'magicLogin'])
    ->name('vendor.magic-login')
    ->middleware('signed');

// Survey-specific magic link - logs in vendor and redirects to survey
Route::get('/portal/survey/{survey}/respond', [VendorAuthController::class, 'surveyMagicLink'])
    ->name('vendor.survey.magic-link')
    ->middleware('signed');

// Vendor Document Download Route (requires vendor auth)
Route::middleware(['auth:vendor'])->group(function () {
    Route::get('/portal/document/{vendorDocument}/download', [VendorDocumentController::class, 'download'])
        ->name('vendor.document.download');
});

// Auditor portal: one revocable link, read-only, scoped to a single audit.
// The token is the capability; there is no account and no export action.
Route::get('/audit-portal/{token}', [AuditPortalController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{64}')
    ->middleware('throttle:60,1')
    ->name('audit-portal.show');

// Trust Center Routes (public)
Route::prefix('trust')->group(function () {
    // Public Trust Center home page
    Route::get('/', [TrustCenterController::class, 'index'])
        ->name('trust-center.index');

    // Public document download
    Route::get('/document/{document}/download', [TrustCenterController::class, 'downloadPublic'])
        ->name('trust-center.document.download');

    // Access request submission (rate limited to prevent spam)
    Route::post('/request-access', [TrustCenterController::class, 'requestAccess'])
        ->name('trust-center.request-access')
        ->middleware('throttle:5,1');

    // Protected access via magic link (signed URL)
    Route::get('/access/{accessRequest}', [TrustCenterController::class, 'protectedAccess'])
        ->name('trust-center.protected-access')
        ->middleware('signed');

    // Protected document download via magic link
    Route::get('/access/{accessRequest}/document/{document}/download', [TrustCenterController::class, 'downloadProtected'])
        ->name('trust-center.protected-download')
        ->middleware('signed');
});
