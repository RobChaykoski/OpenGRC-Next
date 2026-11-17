<?php

namespace App\Casts;

use App\Services\HtmlSanitizerService;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Sanitizes rich text HTML on write and on read.
 *
 * Write covers every save path (Filament forms, REST API, imports). Read covers
 * legacy rows that predate the cast and any field written outside Eloquent, so
 * rendering can no longer depend on every view remembering @safeHtml.
 *
 * Only apply to fields that always hold HTML: plain text would get
 * entity-encoded on read.
 */
class SanitizedHtml implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return app(HtmlSanitizerService::class)->sanitize($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return app(HtmlSanitizerService::class)->sanitize($value);
    }
}
