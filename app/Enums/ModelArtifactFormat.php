<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * The on-disk serialization of a model artifact (ADR 0059).
 *
 * Format decides how the file may be opened, not whether the model is good.
 * A pickle file is executable content; a GGUF or safetensors file is data.
 */
enum ModelArtifactFormat: string implements HasColor, HasLabel
{
    case Gguf = 'gguf';
    case Safetensors = 'safetensors';
    case Pytorch = 'pytorch';
    case Onnx = 'onnx';
    case Unknown = 'unknown';

    public function getLabel(): string
    {
        return match ($this) {
            self::Gguf => 'GGUF',
            self::Safetensors => 'Safetensors',
            self::Pytorch => 'PyTorch (pickle)',
            self::Onnx => 'ONNX',
            self::Unknown => 'Unknown',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pytorch => 'danger',
            self::Onnx => 'warning',
            self::Gguf, self::Safetensors => 'success',
            self::Unknown => 'gray',
        };
    }

    /**
     * Pickle-based serialization can execute code when it is loaded.
     */
    public function executesOnLoad(): bool
    {
        return $this === self::Pytorch;
    }

    /**
     * The format carries a computation graph, not only weights.
     */
    public function containsGraph(): bool
    {
        return $this === self::Onnx;
    }

    public function metadataReadable(): bool
    {
        return in_array($this, [self::Gguf, self::Safetensors], true);
    }

    public static function fromPath(string $path): self
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'gguf' => self::Gguf,
            'safetensors' => self::Safetensors,
            'pt', 'pth', 'pkl', 'ckpt', 'bin' => self::Pytorch,
            'onnx' => self::Onnx,
            default => self::Unknown,
        };
    }
}
