<?php

namespace Tests\Unit\Ai;

use App\Enums\EvidenceSeverity;
use App\Services\Ai\ModelRuntimeBaseline;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModelRuntimeBaselineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['ai_models.runtime_baseline' => [
            'vllm' => ['min' => '0.6.0'],
            'llama.cpp' => ['min' => '3600'],
            'onnxruntime' => ['min' => '1.18.0', 'max' => '1.22.0'],
        ]]);
    }

    #[Test]
    public function a_version_inside_the_baseline_is_supported(): void
    {
        $this->assertNull($this->baseline()->finding('Model', 'vllm', '0.7.1'));
        $this->assertNull($this->baseline()->finding('Model', 'VLLM', 'v0.6.0'));
    }

    #[Test]
    public function a_llama_cpp_build_number_is_compared_as_a_version(): void
    {
        $this->assertNull($this->baseline()->finding('Model', 'llama.cpp', 'b3700'));
        $this->assertNotNull($this->baseline()->finding('Model', 'llama.cpp', 'b3000'));
    }

    #[Test]
    public function a_version_below_the_minimum_is_a_medium_finding(): void
    {
        $finding = $this->baseline()->finding('Llama Test', 'vllm', '0.5.4');

        $this->assertNotNull($finding);
        $this->assertSame(EvidenceSeverity::Medium, $finding['severity']);
        $this->assertSame('Unsupported inference runtime: Llama Test', $finding['title']);
        $this->assertStringContainsString('below the supported minimum 0.6.0', $finding['description']);
    }

    #[Test]
    public function a_version_above_the_maximum_is_a_medium_finding(): void
    {
        $finding = $this->baseline()->finding('Model', 'onnxruntime', '1.23.0');

        $this->assertNotNull($finding);
        $this->assertStringContainsString('above the supported maximum 1.22.0', $finding['description']);
    }

    #[Test]
    public function a_runtime_that_is_not_listed_is_a_finding(): void
    {
        $finding = $this->baseline()->finding('Model', 'ollama', '0.3.0');

        $this->assertNotNull($finding);
        $this->assertStringContainsString('not in the configured supported baseline', $finding['description']);
    }

    #[Test]
    public function a_runtime_without_a_version_cannot_be_shown_to_be_supported(): void
    {
        $finding = $this->baseline()->finding('Model', 'vllm', null);

        $this->assertNotNull($finding);
        $this->assertStringContainsString('without a comparable version', $finding['description']);
    }

    #[Test]
    public function a_version_that_is_not_comparable_is_a_finding(): void
    {
        $finding = $this->baseline()->finding('Model', 'vllm', 'nightly');

        $this->assertNotNull($finding);
        $this->assertStringContainsString('without a comparable version', $finding['description']);
    }

    #[Test]
    public function a_misconfigured_baseline_is_a_finding_rather_than_a_pass(): void
    {
        config(['ai_models.runtime_baseline' => ['vllm' => ['min' => 'latest']]]);

        $finding = $this->baseline()->finding('Model', 'vllm', '0.7.0');

        $this->assertNotNull($finding);
        $this->assertStringContainsString('not a comparable version', $finding['description']);
    }

    #[Test]
    public function no_recorded_runtime_is_not_a_runtime_finding(): void
    {
        $this->assertNull($this->baseline()->finding('Model', null, null));
        $this->assertNull($this->baseline()->finding('Model', '  ', null));
    }

    private function baseline(): ModelRuntimeBaseline
    {
        return new ModelRuntimeBaseline;
    }
}
