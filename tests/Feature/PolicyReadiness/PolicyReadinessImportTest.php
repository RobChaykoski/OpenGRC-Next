<?php

namespace Tests\Feature\PolicyReadiness;

use App\Enums\PolicyDocumentationStatus;
use App\Enums\PolicyEffectivenessStatus;
use App\Enums\PolicyImplementationStatus;
use App\Services\PolicyReadiness\PolicyReadinessWorkbookImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PolicyReadinessImportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_imports_rows_and_maps_the_three_status_dimensions(): void
    {
        $assessment = app(PolicyReadinessWorkbookImporter::class)->importRows(
            $this->rows(),
            '2026 readiness',
            'checklist.xlsx',
        );

        $this->assertSame('2026 readiness', $assessment->name);
        $this->assertSame('checklist.xlsx', $assessment->source_file);
        $this->assertSame(2, $assessment->items()->count());

        $item = $assessment->items()->where('code', 'POL-01')->firstOrFail();

        $this->assertSame('Core', $item->category);
        $this->assertSame('Access Control Policy', $item->title);
        $this->assertSame('High', $item->priority);
        $this->assertSame(PolicyDocumentationStatus::NotStarted, $item->documentation_status);
        $this->assertSame(PolicyImplementationStatus::NotPlanned, $item->implementation_status);
        $this->assertSame(PolicyEffectivenessStatus::NotTested, $item->effectiveness_status);
        $this->assertSame('2026-08-10', $item->last_reviewed);
        $this->assertSame('Security team', $item->owner);
        $this->assertStringContainsString('GV.OC', (string) $item->nist_csf);
    }

    #[Test]
    public function it_reads_a_real_xlsx_workbook(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'policy-readiness-').'.xlsx';

        $writer = new Writer;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['Policy Readiness Checklist']));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues($this->headers()));
        $writer->addRow(Row::fromValues($this->dataRows()[0]));
        $writer->close();

        try {
            $assessment = app(PolicyReadinessWorkbookImporter::class)->importFile(
                $path,
                'From file',
                'workbook.xlsx',
            );

            $this->assertSame(1, $assessment->items()->count());
            $this->assertSame('POL-01', $assessment->items()->first()?->code);
            $this->assertNotNull($assessment->source_sha256);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function a_workbook_with_no_checklist_rows_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(PolicyReadinessWorkbookImporter::class)->importRows(
            [['Title only'], [], $this->headers()],
            'Empty',
        );
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function rows(): array
    {
        return [
            ['Policy Readiness Checklist'],
            ['Sample checklist'],
            [],
            $this->headers(),
            ...$this->dataRows(),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function headers(): array
    {
        return [
            'Category',
            'ID',
            'Policy / Document',
            'Priority',
            'Framework Applicability',
            'NIST CSF 2.0',
            'ITSG-33 Families',
            'CMMC / NIST 800-171 Families',
            'Minimum Content / Control Objective',
            'Suggested Evidence',
            'Applicability',
            'Documentation Status',
            'Implementation Status',
            'Effectiveness Status',
            'Owner',
            'Target Date',
            'Last Reviewed',
            'Evidence Link / Location',
            'Notes / Gaps',
        ];
    }

    /**
     * @return array<int, array<int, string>>
     */
    protected function dataRows(): array
    {
        return [
            [
                'Core',
                'POL-01',
                'Access Control Policy',
                'High',
                'Internal; NIST CSF 2.0',
                'GV.OC, PR.AA',
                'AC, IA',
                'AC, IA',
                'Define access requirements and review them annually.',
                'Approved policy and access review records',
                'Applicable',
                'Not Started',
                'Not Planned',
                'Not Tested',
                'Security team',
                '2026-12-31',
                '2026-08-10',
                'access-control-policy.docx',
                'Complete the approval record.',
            ],
            [
                'Governance',
                'POL-02',
                'Risk Management Policy',
                'High',
                'Internal; NIST CSF 2.0',
                'GV.RM, ID.RA',
                'RA, CA',
                'RA, CA',
                'Define risk criteria and treatment requirements.',
                'Approved policy and risk register',
                'Applicable',
                'In Review',
                'Planned',
                'Not Tested',
                'GRC',
                '2026-12-31',
                '2026-08-10',
                'risk-management-policy.docx',
                'Complete risk appetite and approval.',
            ],
        ];
    }
}
