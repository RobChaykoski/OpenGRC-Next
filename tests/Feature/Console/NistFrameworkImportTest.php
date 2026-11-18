<?php

namespace Tests\Feature\Console;

use App\Services\Frameworks\OscalCsvImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NistFrameworkImportTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_imports_one_nist_framework_and_leaves_it_alone_on_a_second_run(): void
    {
        $this->artisan('opengrc:framework-import', ['code' => 'NIST-CSF-2.0'])
            ->assertSuccessful();

        $standard = DB::table('standards')->where('code', 'NIST-CSF-2.0')->first();

        $this->assertNotNull($standard);
        $this->assertSame('NIST', $standard->authority);
        $this->assertSame(128, DB::table('controls')->where('standard_id', $standard->id)->count());

        $category = DB::table('controls')
            ->where('standard_id', $standard->id)
            ->where('code', 'GV.OC')
            ->first();

        $this->assertNotNull($category);
        $this->assertSame('Organizational Context', $category->title);
        $this->assertStringContainsString('cybersecurity risk management decisions', $category->description);

        $subcategory = DB::table('controls')
            ->where('standard_id', $standard->id)
            ->where('code', 'GV.OC-01')
            ->first();

        $this->assertNotNull($subcategory);
        $this->assertStringNotContainsString('GV.OC-01', $subcategory->title);
        $this->assertNotSame('', $subcategory->discussion);

        $this->artisan('opengrc:framework-import', ['code' => 'NIST-CSF-2.0'])
            ->assertSuccessful();

        $this->assertSame(128, DB::table('controls')->where('standard_id', $standard->id)->count());
    }

    #[Test]
    public function the_importer_is_idempotent_when_called_directly(): void
    {
        $definition = config('framework_catalog.standards')['NIST-AI-RMF-1.0'];
        $importer = app(OscalCsvImporter::class);

        $first = $importer->import('NIST-AI-RMF-1.0', $definition);
        $second = $importer->import('NIST-AI-RMF-1.0', $definition);

        $this->assertSame(91, $first);
        $this->assertSame($first, $second);
        $this->assertSame(1, DB::table('standards')->where('code', 'NIST-AI-RMF-1.0')->count());
    }
}
