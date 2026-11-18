<?php

namespace Tests\Feature\Dlp;

use App\Exceptions\DlpBlockedException;
use App\Models\DlpFinding;
use App\Services\Dlp\DlpService;
use App\Support\UploadRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DlpServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_ai_prompt_with_hr_data_is_blocked_and_recorded_without_the_content(): void
    {
        $service = app(DlpService::class);

        try {
            $service->assertSafeForAi('Employee file for Jane Doe. The background check result is attached.');
            $this->fail('The HR prompt should have been blocked.');
        } catch (DlpBlockedException $exception) {
            $this->assertTrue($exception->result->hasAny(['hr', 'special_category']));
        }

        $finding = DlpFinding::query()->sole();

        $this->assertSame('ai', $finding->source);
        $this->assertSame('blocked', $finding->action);
        $this->assertSame(64, mb_strlen($finding->content_sha256));
        $this->assertStringNotContainsString('Jane Doe', json_encode($finding->getAttributes()));
        $this->assertStringNotContainsString('background check', strtolower(json_encode($finding->getAttributes())));
    }

    #[Test]
    public function a_clean_ai_prompt_is_allowed(): void
    {
        $service = app(DlpService::class);

        $result = $service->assertSafeForAi('Evaluate the access control implementation.');

        $this->assertTrue($result->isEmpty());
        $this->assertSame(0, DlpFinding::query()->count());
    }

    #[Test]
    public function an_upload_with_a_credential_is_blocked(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'notes.txt',
            'api_key = AKIAABCDEFGHIJKLMNOP',
        );

        try {
            app(DlpService::class)->scanUpload($file, UploadRules::EVIDENCE);
            $this->fail('The credential upload should have been blocked.');
        } catch (DlpBlockedException $exception) {
            $this->assertTrue($exception->result->hasAny(['credential']));
        }

        $this->assertSame('blocked', DlpFinding::query()->sole()->action);
    }

    #[Test]
    public function an_upload_with_hr_content_is_flagged_not_blocked(): void
    {
        $file = UploadedFile::fake()->createWithContent(
            'evidence.txt',
            'The employee file contains the performance review.',
        );

        $result = app(DlpService::class)->scanUpload($file, UploadRules::EVIDENCE);

        $this->assertTrue($result->hasAny(['hr']));
        $this->assertSame('flagged', DlpFinding::query()->sole()->action);
    }

    #[Test]
    public function upload_scanning_can_be_disabled(): void
    {
        config(['dlp.uploads.enabled' => false]);

        $file = UploadedFile::fake()->createWithContent('notes.txt', 'api_key = AKIAABCDEFGHIJKLMNOP');

        $result = app(DlpService::class)->scanUpload($file, UploadRules::EVIDENCE);

        $this->assertTrue($result->isEmpty());
        $this->assertSame(0, DlpFinding::query()->count());
    }
}
