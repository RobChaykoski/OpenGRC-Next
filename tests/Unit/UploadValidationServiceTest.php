<?php

namespace Tests\Unit;

use App\Services\UploadValidationService;
use App\Support\UploadRules;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UploadValidationServiceTest extends TestCase
{
    #[Test]
    public function it_accepts_a_real_pdf(): void
    {
        $file = $this->realFile(
            'evidence.pdf',
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<<>>\n%%EOF"
        );

        (new UploadValidationService)->validate($file, UploadRules::EVIDENCE);

        $this->assertTrue(true);
    }

    #[Test]
    public function it_accepts_a_csv_export(): void
    {
        $file = $this->realFile('access-review.csv', "user,role\nrob,admin\n");

        (new UploadValidationService)->validate($file, UploadRules::EVIDENCE);

        $this->assertTrue(true);
    }

    #[Test]
    public function it_rejects_an_executable_extension(): void
    {
        $file = $this->realFile('payload.php', '<?php echo "x";');

        $this->expectException(ValidationException::class);

        (new UploadValidationService)->validate($file, UploadRules::EVIDENCE);
    }

    #[Test]
    public function it_rejects_a_double_extension(): void
    {
        $file = $this->realFile('report.pdf.php', '<?php echo "x";');

        $this->expectException(ValidationException::class);

        (new UploadValidationService)->validate($file, UploadRules::EVIDENCE);
    }

    #[Test]
    public function it_rejects_content_that_does_not_match_an_allowed_type(): void
    {
        // A text file renamed to .pdf: the extension is allowed, the bytes are not.
        $file = $this->realFile('report.pdf', 'this is not a pdf');

        $this->expectException(ValidationException::class);

        (new UploadValidationService)->validate($file, UploadRules::EVIDENCE);
    }

    #[Test]
    public function it_rejects_an_oversized_file(): void
    {
        $file = $this->realFile('dump.txt', str_repeat('a', (UploadRules::maxKb(UploadRules::DOCUMENT) * 1024) + 10));

        $this->expectException(ValidationException::class);

        (new UploadValidationService)->validate($file, UploadRules::DOCUMENT);
    }

    #[Test]
    public function the_evidence_group_does_not_allow_active_content(): void
    {
        $this->assertNotContains('svg', UploadRules::extensions(UploadRules::EVIDENCE));
        $this->assertNotContains('html', UploadRules::extensions(UploadRules::EVIDENCE));
        $this->assertNotContains('js', UploadRules::extensions(UploadRules::EVIDENCE));
    }

    /**
     * A real uploaded file, so MIME detection reads the bytes instead of the name.
     */
    protected function realFile(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'opengrc-upload-');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }
}
