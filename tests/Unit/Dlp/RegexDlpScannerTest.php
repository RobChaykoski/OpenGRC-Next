<?php

namespace Tests\Unit\Dlp;

use App\Services\Dlp\RegexDlpScanner;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RegexDlpScannerTest extends TestCase
{
    #[Test]
    public function clean_control_text_has_no_matches(): void
    {
        $result = (new RegexDlpScanner)->scan('The control is implemented and reviewed quarterly.');

        $this->assertSame([], $result);
    }

    #[Test]
    public function it_detects_personal_data(): void
    {
        $result = (new RegexDlpScanner)->scan('Contact jane.doe@example.test or 555-123-4567.');

        $types = array_map(fn ($match) => $match->type, $result);

        $this->assertContains('personal', $types);
    }

    #[Test]
    public function it_detects_credentials(): void
    {
        $result = (new RegexDlpScanner)->scan('api_key = AKIAABCDEFGHIJKLMNOP');

        $this->assertSame('credential', $result[0]->type);
        $this->assertSame('critical', $result[0]->severity);
    }

    #[Test]
    public function it_detects_special_category_data(): void
    {
        $result = (new RegexDlpScanner)->scan('The criminal record check and medical diagnosis are attached.');

        $types = array_map(fn ($match) => $match->type, $result);

        $this->assertContains('special_category', $types);
    }

    #[Test]
    public function it_detects_hr_data(): void
    {
        $result = (new RegexDlpScanner)->scan('The employee file contains the performance review and salary.');

        $types = array_map(fn ($match) => $match->type, $result);

        $this->assertContains('hr', $types);
    }

    #[Test]
    public function it_detects_a_valid_payment_card_but_not_an_invalid_one(): void
    {
        $valid = (new RegexDlpScanner)->scan('Card 4111 1111 1111 1111');
        $invalid = (new RegexDlpScanner)->scan('Card 1234 5678 9012 3456');

        $this->assertContains('personal', array_map(fn ($match) => $match->type, $valid));
        $this->assertSame([], $invalid);
    }

    #[Test]
    public function it_detects_infrastructure_metadata(): void
    {
        $result = (new RegexDlpScanner)->scan('Host 10.20.30.40, peer 8.8.8.8, database db.corp, metadata 169.254.169.254.');

        $labels = array_map(fn ($match) => $match->label, $result);

        $this->assertContains('Private IPv4 address', $labels);
        $this->assertContains('Public IPv4 address', $labels);
        $this->assertContains('Internal hostname', $labels);
        $this->assertContains('Cloud metadata endpoint', $labels);
    }

    #[Test]
    public function documentation_ranges_are_not_reported_as_public_addresses(): void
    {
        $result = (new RegexDlpScanner)->scan('Use 192.0.2.10, 198.51.100.10, or 203.0.113.10 in examples.');

        $this->assertNotContains('Public IPv4 address', array_map(fn ($match) => $match->label, $result));
    }

    #[Test]
    public function it_detects_configured_answer_key_flags(): void
    {
        $result = (new RegexDlpScanner)->scan('Flag OCR{lab_ready} and TRAILLINE{step_two}.');

        $match = collect($result)->firstWhere('label', 'Assessment answer key');

        $this->assertNotNull($match);
        $this->assertSame('answer_key', $match->type);
        $this->assertSame(2, $match->count);
    }

    #[Test]
    public function answer_key_prefixes_come_from_configuration(): void
    {
        config(['dlp.answer_key_prefixes' => ['FLAG']]);

        $custom = (new RegexDlpScanner)->scan('FLAG{custom_prefix}');
        $legacy = (new RegexDlpScanner)->scan('OCR{legacy_prefix}');

        $this->assertContains('Assessment answer key', array_map(fn ($match) => $match->label, $custom));
        $this->assertSame([], $legacy);
    }
}
