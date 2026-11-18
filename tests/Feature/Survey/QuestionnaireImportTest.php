<?php

namespace Tests\Feature\Survey;

use App\Enums\QuestionType;
use App\Enums\UserRole;
use App\Models\SurveyQuestion;
use App\Models\SurveyTemplate;
use App\Models\User;
use App\Services\Survey\QuestionnaireWorkbookImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QuestionnaireImportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_groups_questions_under_their_section_and_reads_options(): void
    {
        $path = $this->workbook();

        try {
            $parsed = app(QuestionnaireWorkbookImporter::class)->read($path, 'Questions');

            $this->assertCount(2, $parsed['sections']);
            $this->assertSame('Internal Programs', $parsed['sections'][0]['title']);
            $this->assertSame('Perimeter Controls', $parsed['sections'][1]['title']);

            $questions = $parsed['sections'][1]['questions'];

            $this->assertSame(
                'What perimeter security controls are implemented',
                $questions[0]['text'],
            );
            $this->assertSame(QuestionType::MULTIPLE_CHOICE, $questions[0]['type']);
            $this->assertSame(['Firewall', 'IDS', 'Proxy'], $questions[0]['options']);

            $this->assertSame('Is there a central log archive', $questions[1]['text']);
            $this->assertSame(QuestionType::LONG_TEXT, $questions[1]['type']);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function a_yes_no_row_becomes_a_boolean_question(): void
    {
        $path = $this->workbook();

        try {
            $parsed = app(QuestionnaireWorkbookImporter::class)->read($path, 'Questions');
            $question = $parsed['sections'][0]['questions'][0];

            $this->assertSame('Do you maintain a formal privacy policy', $question['text']);
            $this->assertSame(QuestionType::BOOLEAN, $question['type']);
            $this->assertSame(['Yes'], $question['options']);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function it_writes_sectioned_questions_to_a_template(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $template = SurveyTemplate::create([
            'title' => 'Risk assessment',
            'status' => 'active',
            'type' => 'vendor_assessment',
            'created_by_id' => $user->id,
        ]);
        $path = $this->workbook();

        try {
            $result = app(QuestionnaireWorkbookImporter::class)->import($path, $template);

            $this->assertSame(2, $result['sections']);
            $this->assertSame(4, $result['questions']);
            $this->assertSame(4, $template->questions()->count());

            $questions = $template->questions()->get();

            $this->assertSame(['Internal Programs', 'Internal Programs'], $questions->take(2)->pluck('section')->all());
            $this->assertSame(QuestionType::BOOLEAN, $questions[0]->question_type);
            $this->assertSame([['label' => 'Firewall'], ['label' => 'IDS'], ['label' => 'Proxy']], $questions[2]->options);
            $this->assertSame([0, 1, 2, 3], $questions->pluck('sort_order')->all());
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function it_refuses_to_overwrite_questions_unless_asked(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $template = SurveyTemplate::create([
            'title' => 'Risk assessment',
            'status' => 'active',
            'type' => 'vendor_assessment',
            'created_by_id' => $user->id,
        ]);
        SurveyQuestion::create([
            'survey_template_id' => $template->id,
            'question_text' => 'Existing question',
            'question_type' => QuestionType::TEXT,
            'sort_order' => 0,
        ]);

        $path = $this->workbook();
        $importer = app(QuestionnaireWorkbookImporter::class);

        try {
            try {
                $importer->import($path, $template);
                $this->fail('The importer should refuse to overwrite existing questions.');
            } catch (InvalidArgumentException) {
                // Expected.
            }

            $this->assertSame(1, $template->questions()->count());

            $result = $importer->import($path, $template, replace: true);

            $this->assertTrue($result['replaced']);
            $this->assertSame(4, $template->questions()->count());
            $this->assertFalse($template->questions()->where('question_text', 'Existing question')->exists());
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function the_command_imports_and_creates_the_template_once(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $path = $this->workbook();

        try {
            $this->artisan('opengrc:survey:import-questionnaire', [
                'file' => $path,
                '--name' => 'CHC risk assessment',
                '--user' => $admin->id,
            ])
                ->expectsOutputToContain('Imported 4 question(s) across 2 section(s)')
                ->assertSuccessful();

            $template = SurveyTemplate::query()->where('title', 'CHC risk assessment')->firstOrFail();

            $this->assertSame(4, $template->questions()->count());

            $this->artisan('opengrc:survey:import-questionnaire', [
                'file' => $path,
                '--name' => 'CHC risk assessment',
                '--replace' => true,
            ])->assertSuccessful();

            $this->assertSame(4, $template->questions()->count());
        } finally {
            @unlink($path);
        }
    }

    private function workbook(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'questionnaire-').'.xlsx';

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Questions');
        $writer->addRow(Row::fromValues(['Internal Programs']));
        $writer->addRow(Row::fromValues(['', 'Answer']));
        $writer->addRow(Row::fromValues(['Do you maintain a formal privacy policy'."\t".'?Yes']));
        $writer->addRow(Row::fromValues(['How are policies communicated to all staff']));
        $writer->addRow(Row::fromValues(['Perimeter Controls']));
        $writer->addRow(Row::fromValues([
            'What perimeter security controls are implemented:'."\n".'?Firewall'."\n".'?IDS'."\n".'?Proxy',
        ]));
        $writer->addRow(Row::fromValues(['Is there a central log archive']));
        $writer->close();

        return $path;
    }
}
