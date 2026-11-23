<?php

namespace Tests\Feature;

use App\Enums\FindingStatus;
use App\Enums\ModelArtifactFormat;
use App\Enums\ModelArtifactStatus;
use App\Models\Finding;
use App\Models\ModelArtifact;
use App\Models\User;
use App\Services\Ai\ModelArtifactRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ModelArtifactRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private string $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workspace = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ocr-model-'.bin2hex(random_bytes(6));
        mkdir($this->workspace, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->workspace);

        parent::tearDown();
    }

    #[Test]
    public function the_gguf_reader_reads_metadata_and_ignores_tensor_bytes(): void
    {
        $path = $this->gguf('model.gguf', [
            'general.architecture' => 'llama',
            'general.name' => 'Range Test 1B',
            'general.file_type' => 15,
            'llama.context_length' => 4096,
            'tokenizer.chat_template' => '{{ messages }}',
        ]);

        $registrar = app(ModelArtifactRegistrar::class);
        $preview = $registrar->preview($path);

        $this->assertSame(ModelArtifactFormat::Gguf, $preview['format']);
        $this->assertSame('llama', $preview['architecture']);
        $this->assertSame('Range Test 1B', $preview['name']);
        $this->assertSame(4096, $preview['context_length']);
        $this->assertSame(12, $preview['tensor_count']);
        $this->assertSame('Q4_K_M', $preview['quantization']);
        $this->assertSame(hash('sha256', '{{ messages }}'), $preview['chat_template_hash']);
        $this->assertNotEmpty($preview['content_hash']);
    }

    #[Test]
    public function a_pickle_artifact_cannot_be_approved(): void
    {
        $path = $this->artifactDirectory([
            'pytorch_model.bin' => 'not-a-real-pickle',
            'config.json' => '{"model_type":"llama"}',
        ]);

        $this->expectException(RuntimeException::class);

        app(ModelArtifactRegistrar::class)->register(
            $path,
            ['name' => 'Pickle Model', 'source' => 'https://example.test/model', 'revision' => 'abc123'],
            true,
            true,
            User::factory()->create()->getKey(),
        );
    }

    #[Test]
    public function a_pickle_artifact_raises_an_unsafe_serialization_finding(): void
    {
        $path = $this->artifactDirectory(['pytorch_model.bin' => 'not-a-real-pickle']);

        $result = app(ModelArtifactRegistrar::class)->register($path, [
            'name' => 'Pickle Model',
            'source' => 'https://example.test/model',
            'revision' => 'abc123',
            'license' => 'apache-2.0',
        ]);

        $this->assertSame(ModelArtifactFormat::Pytorch, $result['artifact']->format);
        $this->assertContains('unsafe-serialization', $result['findings']);
        $this->assertDatabaseHas('findings', [
            'model_artifact_id' => $result['artifact']->getKey(),
            'severity' => 'high',
            'status' => FindingStatus::Open->value,
        ]);
    }

    #[Test]
    public function safetensors_registration_hashes_every_file_and_records_provenance(): void
    {
        $path = $this->artifactDirectory([
            'model.safetensors' => $this->safetensorsBytes(3),
            'config.json' => '{"model_type":"llama","max_position_embeddings":8192,"license":"apache-2.0"}',
            'tokenizer.json' => '{"model":{"type":"BPE"}}',
            'tokenizer_config.json' => '{"chat_template":"{{ messages }}"}',
        ]);

        $result = app(ModelArtifactRegistrar::class)->register($path, [
            'name' => 'Safe Model',
            'source' => 'https://example.test/model',
            'revision' => 'abc123',
        ]);

        $artifact = $result['artifact'];

        $this->assertSame(ModelArtifactFormat::Safetensors, $artifact->format);
        $this->assertSame('llama', $artifact->architecture);
        $this->assertSame(8192, $artifact->context_length);
        $this->assertSame(4, $artifact->file_count);
        $this->assertSame('verified', $artifact->provenance_status);
        $this->assertSame(4, $artifact->files()->whereNotNull('sha256')->count());
        $this->assertNotNull($artifact->tokenizer_hash);
        $this->assertNotNull($artifact->chat_template_hash);
        $this->assertNotContains('unsafe-serialization', $result['findings']);
        $this->assertNotContains('provenance', $result['findings']);
    }

    #[Test]
    public function changed_bytes_raise_substitution_and_withdraw_approval(): void
    {
        $path = $this->artifactDirectory(['model.safetensors' => $this->safetensorsBytes(2)]);
        $owner = User::factory()->create();

        $first = app(ModelArtifactRegistrar::class)->register(
            $path,
            ['name' => 'Versioned Model', 'source' => 'https://example.test/model', 'revision' => 'abc123', 'license' => 'apache-2.0'],
            true,
            true,
            $owner->getKey(),
        );

        $this->assertTrue($first['artifact']->isApproved());

        file_put_contents($path.DIRECTORY_SEPARATOR.'model.safetensors', $this->safetensorsBytes(5));

        $second = app(ModelArtifactRegistrar::class)->register(
            $path,
            ['name' => 'Versioned Model', 'source' => 'https://example.test/model', 'revision' => 'abc123', 'license' => 'apache-2.0'],
        );

        $this->assertSame($first['artifact']->getKey(), $second['artifact']->getKey());
        $this->assertContains('substitution', $second['findings']);
        $this->assertSame(ModelArtifactStatus::Proposed, $second['artifact']->status);
        $this->assertNull($second['artifact']->approved_at);
    }

    #[Test]
    public function a_changed_chat_template_raises_behaviour_drift(): void
    {
        $path = $this->artifactDirectory([
            'model.safetensors' => $this->safetensorsBytes(2),
            'tokenizer_config.json' => '{"chat_template":"version one"}',
        ]);

        $attributes = ['name' => 'Drift Model', 'source' => 'https://example.test/model', 'revision' => 'abc123', 'license' => 'apache-2.0'];

        app(ModelArtifactRegistrar::class)->register($path, $attributes);

        file_put_contents($path.DIRECTORY_SEPARATOR.'tokenizer_config.json', '{"chat_template":"version two"}');

        $second = app(ModelArtifactRegistrar::class)->register($path, $attributes);

        $this->assertContains('template-drift', $second['findings']);
        $this->assertDatabaseHas('findings', [
            'model_artifact_id' => $second['artifact']->getKey(),
            'dedupe_key' => 'model-artifact:'.$second['artifact']->getKey().':template-drift',
        ]);
    }

    #[Test]
    public function the_dry_run_command_writes_nothing(): void
    {
        $path = $this->artifactDirectory(['model.safetensors' => $this->safetensorsBytes(2)]);

        $this->artisan('opengrc:model-artifact:register', [
            'path' => $path,
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame(0, ModelArtifact::query()->count());
        $this->assertSame(0, Finding::query()->count());
    }

    /**
     * @param  array<string, string>  $files
     */
    private function artifactDirectory(array $files): string
    {
        $path = $this->workspace.DIRECTORY_SEPARATOR.bin2hex(random_bytes(4));
        mkdir($path, 0777, true);

        foreach ($files as $name => $contents) {
            $full = $path.DIRECTORY_SEPARATOR.$name;
            file_put_contents($full, $contents);
        }

        return $path;
    }

    /**
     * @param  array<string, string|int>  $metadata
     */
    private function gguf(string $name, array $metadata, int $tensorCount = 12): string
    {
        $path = $this->workspace.DIRECTORY_SEPARATOR.$name;
        $bytes = 'GGUF'.pack('V', 3).pack('P', $tensorCount).pack('P', count($metadata));

        foreach ($metadata as $key => $value) {
            $bytes .= pack('P', strlen($key)).$key;

            if (is_int($value)) {
                $bytes .= pack('V', 4).pack('V', $value);
            } else {
                $bytes .= pack('V', 8).pack('P', strlen($value)).$value;
            }
        }

        // Tensor data the reader must never touch.
        $bytes .= random_bytes(64);
        file_put_contents($path, $bytes);

        return $path;
    }

    private function safetensorsBytes(int $tensorCount): string
    {
        $header = [];

        for ($index = 0; $index < $tensorCount; $index++) {
            $header["tensor.{$index}"] = [
                'dtype' => 'F16',
                'shape' => [2, 2],
                'data_offsets' => [$index * 8, ($index + 1) * 8],
            ];
        }

        $json = (string) json_encode($header);

        return pack('P', strlen($json)).$json.str_repeat("\0", $tensorCount * 8);
    }

    private function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        ) as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($path);
    }
}
