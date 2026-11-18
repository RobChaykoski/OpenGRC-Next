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
}
