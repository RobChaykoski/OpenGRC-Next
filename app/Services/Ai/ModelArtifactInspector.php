<?php

namespace App\Services\Ai;

use App\Enums\ModelArtifactFileKind;
use App\Enums\ModelArtifactFormat;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;

/**
 * Inspect a model artifact without executing it or loading its weights.
 *
 * The inspector walks a file or directory, hashes each file, reads only the
 * metadata header of GGUF and safetensors files, and never calls a Python or
 * PyTorch loader. A pickle artifact is hashed and classified, not opened.
 */
class ModelArtifactInspector
{
    private const MAX_FILES = 2000;

    private const SKIP_DIRECTORIES = ['.git', '__pycache__', 'node_modules', '.cache'];

    public function __construct(
        protected GgufReader $gguf,
        protected SafetensorsReader $safetensors,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function inspect(string $path, bool $hashFiles = true): array
    {
        $path = rtrim($path, DIRECTORY_SEPARATOR);

        if (! file_exists($path)) {
            throw new RuntimeException("Artifact path does not exist: {$path}");
        }

        $root = is_dir($path) ? $path : dirname($path);
        $files = $this->files($path, $hashFiles);
        $format = $this->detectFormat($files);
        $warnings = [];

        $inspection = [
            'name' => $this->defaultName($path),
            'format' => $format,
            'architecture' => null,
            'quantization' => null,
            'license' => null,
            'context_length' => null,
            'tensor_count' => null,
            'file_count' => count($files),
            'total_bytes' => array_sum(array_column($files, 'size_bytes')),
            'content_hash' => $this->contentHash($files),
            'tokenizer_hash' => $this->tokenizerHash($files),
            'chat_template_hash' => null,
            'metadata' => [],
            'warnings' => $warnings,
            'files' => $files,
            'root' => $root,
        ];

        $primary = $this->primaryFile($path, $files, $format);

        if ($primary === null) {
            return $inspection;
        }

        try {
            $inspection = match ($format) {
                ModelArtifactFormat::Gguf => $this->inspectGguf($primary, $inspection),
                ModelArtifactFormat::Safetensors => $this->inspectSafetensors($primary, $inspection),
                default => $inspection,
            };
        } catch (Throwable $exception) {
            $inspection['warnings'][] = 'Metadata not read: '.$exception->getMessage();
            $inspection['metadata']['metadata_error'] = $exception->getMessage();
        }

        $inspection['chat_template_hash'] ??= $this->chatTemplateHash($root, $files);

        return $inspection;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function files(string $path, bool $hashFiles): array
    {
        if (is_file($path)) {
            return [$this->describeFile($path, dirname($path), $hashFiles)];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                continue;
            }

            if ($file->isLink()) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($path) + 1));

            if ($this->skipped($relative)) {
                continue;
            }

            if (count($files) >= self::MAX_FILES) {
                throw new RuntimeException('Artifact has more than '.self::MAX_FILES.' files; split it or raise the limit in code.');
            }

            $files[] = $this->describeFile($file->getPathname(), $path, $hashFiles, $relative);
        }

        usort($files, fn (array $left, array $right): int => strcmp($left['path'], $right['path']));

        return $files;
    }

    /**
     * @return array<string, mixed>
     */
    private function describeFile(string $fullPath, string $root, bool $hashFiles, ?string $relative = null): array
    {
        $relative ??= basename($fullPath);
        $size = (int) filesize($fullPath);

        return [
            'path' => str_replace('\\', '/', $relative),
            'kind' => ModelArtifactFileKind::fromPath($relative),
            'size_bytes' => $size,
            'sha256' => $hashFiles ? hash_file('sha256', $fullPath) : null,
        ];
    }

    private function skipped(string $relative): bool
    {
        foreach (explode('/', $relative) as $segment) {
            if (in_array($segment, self::SKIP_DIRECTORIES, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $files
     */
    private function detectFormat(array $files): ModelArtifactFormat
    {
        $formats = array_map(
            fn (array $file): ModelArtifactFormat => $file['kind'] === ModelArtifactFileKind::Weights
                ? ModelArtifactFormat::fromPath($file['path'])
                : ModelArtifactFormat::Unknown,
            $files,
        );

        foreach ([ModelArtifactFormat::Gguf, ModelArtifactFormat::Safetensors, ModelArtifactFormat::Onnx, ModelArtifactFormat::Pytorch] as $candidate) {
            if (in_array($candidate, $formats, true)) {
                return $candidate;
            }
        }

        return ModelArtifactFormat::Unknown;
    }

    /**
     * @param  array<int, array<string, mixed>>  $files
     */
    private function primaryFile(string $path, array $files, ModelArtifactFormat $format): ?string
    {
        if (is_file($path)) {
            return $path;
        }

        $extension = match ($format) {
            ModelArtifactFormat::Gguf => '.gguf',
            ModelArtifactFormat::Safetensors => '.safetensors',
            ModelArtifactFormat::Onnx => '.onnx',
            ModelArtifactFormat::Pytorch => null,
            ModelArtifactFormat::Unknown => null,
        };

        if ($extension === null) {
            return null;
        }

        foreach ($files as $file) {
            if (str_ends_with(strtolower($file['path']), $extension)) {
                return $path.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file['path']);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $inspection
     * @return array<string, mixed>
     */
    private function inspectGguf(string $path, array $inspection): array
    {
        $header = $this->gguf->read($path);
        $metadata = $header['metadata'];
        $architecture = $this->string($metadata['general.architecture'] ?? null);

        $inspection['tensor_count'] = $header['tensor_count'];
        $inspection['name'] = $this->string($metadata['general.name'] ?? null) ?? $inspection['name'];
        $inspection['architecture'] = $architecture;
        $inspection['license'] = $this->string($metadata['general.license.name'] ?? null)
            ?? $this->string($metadata['general.license'] ?? null);
        $inspection['context_length'] = $architecture !== null
            ? $this->integer($metadata[$architecture.'.context_length'] ?? null)
            : null;
        $inspection['quantization'] = $this->quantization(
            $this->integer($metadata['general.file_type'] ?? null),
            $inspection['files'],
        );

        $template = $this->string($metadata['tokenizer.chat_template'] ?? null);

        if ($template !== null) {
            $inspection['chat_template_hash'] = hash('sha256', $template);
        }

        $inspection['metadata'] = [
            'gguf_version' => $header['version'],
            'general.name' => $inspection['name'],
            'general.architecture' => $architecture,
            'general.file_type' => $this->integer($metadata['general.file_type'] ?? null),
            'general.quantization_version' => $this->integer($metadata['general.quantization_version'] ?? null),
            'tokenizer.ggml.model' => $this->string($metadata['tokenizer.ggml.model'] ?? null),
            'context_length' => $inspection['context_length'],
            'block_count' => $architecture !== null
                ? $this->integer($metadata[$architecture.'.block_count'] ?? null)
                : null,
        ];

        if (! $architecture) {
            $inspection['warnings'][] = 'GGUF metadata does not declare general.architecture.';
        }

        return $inspection;
    }

    /**
     * @param  array<string, mixed>  $inspection
     * @return array<string, mixed>
     */
    private function inspectSafetensors(string $path, array $inspection): array
    {
        $header = $this->safetensors->read($path);
        $inspection['tensor_count'] = $header['tensor_count'];

        $config = $this->jsonFile(dirname($path).DIRECTORY_SEPARATOR.'config.json');
        $modelType = $this->string($config['model_type'] ?? null);
        $architectures = $config['architectures'] ?? [];
        $architecture = $modelType
            ?? (is_array($architectures) && isset($architectures[0]) ? $this->string($architectures[0]) : null);

        $inspection['architecture'] = $architecture;
        $inspection['context_length'] = $this->integer($config['max_position_embeddings'] ?? null);
        $inspection['license'] = $this->string($config['license_name'] ?? null)
            ?? $this->string($config['license'] ?? null);
        $inspection['quantization'] = $this->quantization(null, $inspection['files'])
            ?? $this->string($header['metadata']['quantization'] ?? null);

        $inspection['metadata'] = [
            'tensor_count' => $header['tensor_count'],
            'dtypes' => $header['dtypes'],
            'model_type' => $modelType,
            'architectures' => $architectures,
            'context_length' => $inspection['context_length'],
            'safetensors_metadata' => array_keys($header['metadata']),
        ];

        foreach (['config.json', 'tokenizer_config.json', 'tokenizer.json', 'chat_template.jinja'] as $name) {
            $relative = str_replace('\\', '/', $name);

            foreach ($inspection['files'] as $file) {
                if ($file['path'] === $relative && $file['sha256'] === null) {
                    $inspection['warnings'][] = "Hash for {$name} was skipped; behaviour drift cannot be detected.";
                }
            }
        }

        return $inspection;
    }

    /**
     * @param  array<int, array<string, mixed>>  $files
     */
    private function contentHash(array $files): ?string
    {
        $lines = [];

        foreach ($files as $file) {
            if ($file['sha256'] === null) {
                return null;
            }

            $lines[] = $file['path']."\0".$file['sha256'];
        }

        sort($lines);

        return hash('sha256', implode("\n", $lines));
    }

    /**
     * @param  array<int, array<string, mixed>>  $files
     */
    private function tokenizerHash(array $files): ?string
    {
        $lines = [];

        foreach ($files as $file) {
            if ($file['kind'] !== ModelArtifactFileKind::Tokenizer || $file['sha256'] === null) {
                continue;
            }

            $lines[] = $file['path']."\0".$file['sha256'];
        }

        if ($lines === []) {
            return null;
        }

        sort($lines);

        return hash('sha256', implode("\n", $lines));
    }

    /**
     * @param  array<int, array<string, mixed>>  $files
     */
    private function chatTemplateHash(string $root, array $files): ?string
    {
        $candidates = ['chat_template.jinja', 'chat_template.txt'];

        foreach ($candidates as $candidate) {
            $full = $root.DIRECTORY_SEPARATOR.$candidate;

            if (is_file($full)) {
                return hash_file('sha256', $full);
            }
        }

        $config = $this->jsonFile($root.DIRECTORY_SEPARATOR.'tokenizer_config.json');

        if (isset($config['chat_template'])) {
            $template = $config['chat_template'];

            if (is_string($template)) {
                return hash('sha256', $template);
            }

            if (is_array($template)) {
                return hash('sha256', json_encode($template));
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $files
     */
    private function quantization(?int $fileType, array $files): ?string
    {
        foreach ($files as $file) {
            if (preg_match('/(IQ\d+_[A-Z]+|Q\d+_K_[A-Z]+|Q\d+_\d+|BF16|F16|F32)/i', $file['path'], $matches)) {
                return strtoupper($matches[1]);
            }
        }

        return match ($fileType) {
            0 => 'F32',
            1 => 'F16',
            2 => 'Q4_0',
            3 => 'Q4_1',
            7 => 'Q8_0',
            8 => 'Q5_0',
            9 => 'Q5_1',
            10 => 'Q2_K',
            11 => 'Q3_K_S',
            12 => 'Q3_K_M',
            13 => 'Q3_K_L',
            14 => 'Q4_K_S',
            15 => 'Q4_K_M',
            16 => 'Q5_K_S',
            17 => 'Q5_K_M',
            18 => 'Q6_K',
            default => null,
        };
    }

    private function defaultName(string $path): string
    {
        if (is_dir($path)) {
            return basename($path);
        }

        return pathinfo($path, PATHINFO_FILENAME);
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonFile(string $path): array
    {
        if (! is_file($path) || filesize($path) > 10 * 1024 * 1024) {
            return [];
        }

        try {
            $decoded = json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function string(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    private function integer(mixed $value): ?int
    {
        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : null;
    }
}
