<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * What a file inside a model artifact does (ADR 0059).
 *
 * Behaviour can change through a tokenizer or template without a weight change,
 * so those files are named rather than treated as generic attachments.
 */
enum ModelArtifactFileKind: string implements HasLabel
{
    case Weights = 'weights';
    case Config = 'config';
    case Tokenizer = 'tokenizer';
    case Template = 'template';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Weights => 'Weights',
            self::Config => 'Configuration',
            self::Tokenizer => 'Tokenizer',
            self::Template => 'Chat template',
            self::Other => 'Other',
        };
    }

    public static function fromPath(string $path): self
    {
        $name = strtolower(basename($path));

        return match (true) {
            str_ends_with($name, '.gguf'),
            str_ends_with($name, '.safetensors'),
            str_ends_with($name, '.onnx'),
            str_ends_with($name, '.pt'),
            str_ends_with($name, '.pth'),
            str_ends_with($name, '.pkl'),
            str_ends_with($name, '.bin'),
            str_ends_with($name, '.ckpt') => self::Weights,
            str_contains($name, 'tokenizer') => self::Tokenizer,
            str_contains($name, 'chat_template'),
            str_ends_with($name, '.jinja') => self::Template,
            in_array($name, ['config.json', 'generation_config.json', 'special_tokens_map.json'], true) => self::Config,
            default => self::Other,
        };
    }
}
