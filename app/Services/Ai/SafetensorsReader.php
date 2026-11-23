<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * Read the JSON header of a safetensors file without loading tensor data.
 *
 * The first eight bytes are a little-endian unsigned length, followed by a JSON
 * header that names every tensor and its dtype, shape and byte offsets. The
 * optional "__metadata__" object carries free-form strings.
 */
class SafetensorsReader
{
    private const MAX_HEADER_BYTES = 100 * 1024 * 1024;

    /**
     * @return array{tensor_count: int, dtypes: array<int, string>, metadata: array<string, string>}
     */
    public function read(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("Safetensors file is not readable: {$path}");
        }

        $size = (int) filesize($path);
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Cannot open safetensors file: {$path}");
        }

        try {
            $prefix = fread($handle, 8);

            if ($prefix === false || strlen($prefix) !== 8) {
                throw new RuntimeException('Safetensors file is too short.');
            }

            $length = unpack('P', $prefix)[1];

            if ($length < 2 || $length > self::MAX_HEADER_BYTES || $length > $size - 8) {
                throw new RuntimeException('Safetensors header length is invalid.');
            }

            $json = '';

            while (strlen($json) < $length) {
                $chunk = fread($handle, $length - strlen($json));

                if ($chunk === false || $chunk === '') {
                    throw new RuntimeException('Unexpected end of safetensors header.');
                }

                $json .= $chunk;
            }
        } finally {
            fclose($handle);
        }

        try {
            $header = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Safetensors header is not valid JSON.', 0, $exception);
        }

        if (! is_array($header)) {
            throw new RuntimeException('Safetensors header is not an object.');
        }

        $metadata = [];
        $rawMetadata = $header['__metadata__'] ?? [];

        if (is_array($rawMetadata)) {
            foreach ($rawMetadata as $key => $value) {
                if (is_scalar($value)) {
                    $metadata[(string) $key] = (string) $value;
                }
            }
        }

        $dtypes = [];
        $tensorCount = 0;

        foreach ($header as $name => $tensor) {
            if ($name === '__metadata__') {
                continue;
            }

            $tensorCount++;

            if (is_array($tensor) && isset($tensor['dtype']) && is_string($tensor['dtype'])) {
                $dtypes[$tensor['dtype']] = true;
            }
        }

        return [
            'tensor_count' => $tensorCount,
            'dtypes' => array_keys($dtypes),
            'metadata' => $metadata,
        ];
    }
}
