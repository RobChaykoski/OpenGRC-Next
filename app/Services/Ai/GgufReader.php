<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * Read the metadata header of a GGUF file without loading tensor data.
 *
 * GGUF is a little-endian binary format: magic, version, tensor count, a
 * key-value metadata block, tensor descriptors, then tensor data. This reader
 * stops after the metadata block. The metadata is self-declared by whoever
 * wrote the file; it is evidence of a claim, not proof of provenance.
 *
 * Spec: https://github.com/ggml-org/ggml/blob/master/docs/gguf.md
 */
class GgufReader
{
    private const MAGIC = 'GGUF';

    private const TYPE_UINT8 = 0;

    private const TYPE_INT8 = 1;

    private const TYPE_UINT16 = 2;

    private const TYPE_INT16 = 3;

    private const TYPE_UINT32 = 4;

    private const TYPE_INT32 = 5;

    private const TYPE_FLOAT32 = 6;

    private const TYPE_BOOL = 7;

    private const TYPE_STRING = 8;

    private const TYPE_ARRAY = 9;

    private const TYPE_UINT64 = 10;

    private const TYPE_INT64 = 11;

    private const TYPE_FLOAT64 = 12;

    private const MAX_STRING_BYTES = 4 * 1024 * 1024;

    private const MAX_ARRAY_ITEMS = 100_000;

    private const MAX_DEPTH = 4;

    /** @var resource */
    private $handle;

    private int $size;

    private int $offset = 0;

    /**
     * @return array{version: int, tensor_count: int, metadata: array<string, mixed>}
     */
    public function read(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("GGUF file is not readable: {$path}");
        }

        $this->size = (int) filesize($path);
        $this->offset = 0;
        $this->handle = fopen($path, 'rb');

        if ($this->handle === false) {
            throw new RuntimeException("Cannot open GGUF file: {$path}");
        }

        try {
            if ($this->bytes(4) !== self::MAGIC) {
                throw new RuntimeException('Not a GGUF file: magic mismatch.');
            }

            $version = $this->uint32();
            $tensorCount = $this->uint64();
            $kvCount = $this->uint64();

            if ($version < 2 || $version > 3) {
                throw new RuntimeException("Unsupported GGUF version: {$version}.");
            }

            if ($tensorCount > 10_000_000 || $kvCount > 1_000_000) {
                throw new RuntimeException('GGUF header declares an implausible count.');
            }

            $metadata = [];

            for ($index = 0; $index < $kvCount; $index++) {
                $key = $this->string();
                $type = $this->uint32();
                $metadata[$key] = $this->value($type, 0, true);
            }

            return [
                'version' => $version,
                'tensor_count' => $tensorCount,
                'metadata' => $metadata,
            ];
        } finally {
            fclose($this->handle);
        }
    }

    private function value(int $type, int $depth, bool $retain): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            throw new RuntimeException('GGUF metadata nests too deeply.');
        }

        return match ($type) {
            self::TYPE_UINT8 => $this->integer(unpack('C', $this->bytes(1))[1]),
            self::TYPE_INT8 => $this->integer(unpack('c', $this->bytes(1))[1]),
            self::TYPE_UINT16 => $this->integer(unpack('v', $this->bytes(2))[1]),
            self::TYPE_INT16 => $this->integer(unpack('s', $this->bytes(2))[1]),
            self::TYPE_UINT32 => $this->uint32(),
            self::TYPE_INT32 => $this->integer(unpack('l', $this->bytes(4))[1]),
            self::TYPE_FLOAT32 => unpack('g', $this->bytes(4))[1],
            self::TYPE_BOOL => $this->bytes(1) !== "\x00",
            self::TYPE_STRING => $this->string(),
            self::TYPE_ARRAY => $this->arrayValue($depth, $retain),
            self::TYPE_UINT64 => $this->uint64(),
            self::TYPE_INT64 => $this->integer(unpack('P', $this->bytes(8))[1]),
            self::TYPE_FLOAT64 => unpack('e', $this->bytes(8))[1],
            default => throw new RuntimeException("Unknown GGUF metadata type: {$type}."),
        };
    }

    /**
     * @return array<int, mixed>|null
     */
    private function arrayValue(int $depth, bool $retain): ?array
    {
        $type = $this->uint32();
        $count = $this->uint64();

        if ($count > self::MAX_ARRAY_ITEMS) {
            throw new RuntimeException('GGUF metadata array is too large.');
        }

        $items = [];

        for ($index = 0; $index < $count; $index++) {
            $value = $this->value($type, $depth + 1, $retain && $count <= 4096);

            if ($retain && $count <= 4096) {
                $items[] = $value;
            }
        }

        return $retain ? $items : null;
    }

    private function string(): string
    {
        $length = $this->uint64();

        if ($length > self::MAX_STRING_BYTES) {
            throw new RuntimeException('GGUF metadata string is too large.');
        }

        return $this->bytes($length);
    }

    private function bytes(int $length): string
    {
        if ($length < 0 || $this->offset + $length > $this->size) {
            throw new RuntimeException('GGUF metadata runs past the end of the file.');
        }

        $chunk = '';

        while (strlen($chunk) < $length) {
            $read = fread($this->handle, $length - strlen($chunk));

            if ($read === false || $read === '') {
                throw new RuntimeException('Unexpected end of GGUF file.');
            }

            $chunk .= $read;
        }

        $this->offset += $length;

        return $chunk;
    }

    private function uint32(): int
    {
        return $this->integer(unpack('V', $this->bytes(4))[1]);
    }

    private function uint64(): int
    {
        $value = unpack('P', $this->bytes(8))[1];

        // PHP integers are signed 64-bit; a count above PHP_INT_MAX is not a
        // plausible header value and would break arithmetic downstream.
        if ($value < 0) {
            throw new RuntimeException('GGUF header contains an out-of-range value.');
        }

        return $value;
    }

    private function integer(int|float $value): int
    {
        return (int) $value;
    }
}
