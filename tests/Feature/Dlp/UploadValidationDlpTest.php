<?php

namespace Tests\Feature\Dlp;

use App\Models\DlpFinding;
use App\Services\UploadValidationService;
use App\Support\UploadRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UploadValidationDlpTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_upload_with_a_credential_is_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'evidence.txt',
            'api_key = AKIAABCDEFGHIJKLMNOP',
        );

        $this->expectException(ValidationException::class);

        app(UploadValidationService::class)->validate($file, UploadRules::EVIDENCE);
    }

    #[Test]
    public function an_hr_upload_is_flagged_but_allowed(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'evidence.txt',
            'The employee file contains the performance review.',
        );

        app(UploadValidationService::class)->validate($file, UploadRules::EVIDENCE);

        $this->assertSame('flagged', DlpFinding::query()->sole()->action);
    }
}
