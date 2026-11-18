<?php

namespace App\Services\Documents;

use App\Models\DocumentTemplate;
use Illuminate\Support\Facades\Storage;

/**
 * Parse an uploaded template and copy its extracted content into the model data.
 */
class DocumentTemplateManager
{
    public function __construct(
        protected DocumentTemplateParser $parser,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function prepare(array $data, ?DocumentTemplate $existing = null, bool $force = false): array
    {
        $source = $data['source_file'] ?? null;
        $source = is_array($source) ? (string) array_values($source)[0] : (string) $source;

        if ($source === '') {
            return $data;
        }

        if (! $force
            && $existing !== null
            && $existing->source_file === $source
            && filled($existing->body_text)) {
            return $data;
        }

        $absolute = is_file($source) ? $source : Storage::disk('private')->path($source);
        $parsed = $this->parser->parse($absolute);

        $data['source_file'] = $source;
        $data['body_text'] = $parsed['body'];
        $data['placeholders'] = $parsed['placeholders'];
        $data['outline'] = $parsed['outline'];
        $data['source_sha256'] = $parsed['source_sha256'];

        return $data;
    }
}
