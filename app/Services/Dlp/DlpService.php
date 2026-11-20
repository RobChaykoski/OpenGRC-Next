<?php

namespace App\Services\Dlp;

use App\Exceptions\DlpBlockedException;
use App\Models\DlpFinding;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use OpenSpout\Reader\XLSX\Reader;
use Throwable;
use ZipArchive;

/**
 * Content scanning for AI egress and uploads.
 *
 * AI: HR, special-category, and credential content is a hard block. It is not
 * a configurable toggle, because the consequence of sending it is irreversible.
 *
 * Uploads: credentials are blocked; HR and special-category content is flagged
 * for review. Background-check evidence must be storable, but it must not pass
 * silently.
 */
class DlpService
{
    public function __construct(
        protected RegexDlpScanner $regex,
        protected PresidioDlpScanner $presidio,
        protected DlpIdentifierCatalog $catalog,
    ) {}

    public function scan(string $text): DlpScanResult
    {
        $text = mb_substr($text, 0, 500_000);

        $matches = $this->regex->scan($text);
        $matches = array_merge($matches, $this->presidio->scan($text));

        $merged = [];

        foreach ($matches as $match) {
            $existing = $merged[$match->type] ?? null;

            if ($existing === null || $match->count > $existing->count) {
                $merged[$match->type] = $match;
            }
        }

        return new DlpScanResult(
            array_values($merged),
            $this->presidio->isConfigured() ? 'regex+presidio' : 'regex',
        );
    }

    /**
     * @throws DlpBlockedException
     */
    public function assertSafeForAi(string $text, ?Model $context = null): DlpScanResult
    {
        $result = $this->scan($text);
        $blockedTypes = (array) config('dlp.ai.block_types', ['credential', 'special_category', 'hr']);

        if ($result->hasAny($blockedTypes)) {
            $this->record('ai', 'blocked', $result, $text, $context);

            throw new DlpBlockedException(
                $result,
                'This content contains data that cannot be sent to an AI provider: '.$result->summary().'.',
            );
        }

        if (! $result->isEmpty()) {
            $this->record('ai', 'flagged', $result, $text, $context);
        }

        return $result;
    }

    /**
     * @throws DlpBlockedException
     */
    public function scanUpload(
        UploadedFile $file,
        string $group = 'evidence',
        ?Model $context = null,
    ): DlpScanResult {
        if (! config('dlp.uploads.enabled', true)) {
            return new DlpScanResult;
        }

        $text = $this->extractText($file);

        if ($text === null || trim($text) === '') {
            return new DlpScanResult;
        }

        $result = $this->scan($text);

        if ($result->isEmpty()) {
            return $result;
        }

        $blocked = $result->hasAny((array) config('dlp.uploads.block_types', ['credential']));

        $this->record(
            'upload',
            $blocked ? 'blocked' : 'flagged',
            $result,
            $text,
            $context,
        );

        if ($blocked) {
            throw new DlpBlockedException(
                $result,
                'This file contains credentials or other blocked data: '.$result->summary().'.',
            );
        }

        return $result;
    }

    protected function record(
        string $source,
        string $action,
        DlpScanResult $result,
        string $text,
        ?Model $context,
    ): void {
        DlpFinding::create([
            'source' => $source,
            'context_type' => $context?->getMorphClass(),
            'context_id' => $context?->getKey(),
            'user_id' => auth()->id(),
            'severity' => $result->highestSeverity(),
            'match_types' => $result->labels(),
            'identifier_types' => $this->catalog->codesForLabels($result->labels()),
            'action' => $action,
            'content_sha256' => hash('sha256', $text),
        ]);
    }

    /**
     * Extract text from formats we can inspect without OCR or a PDF service.
     * Returns null when the file is opaque (image, PDF, archive).
     */
    protected function extractText(UploadedFile $file): ?string
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $path = $file->getRealPath();
        $maxBytes = (int) config('dlp.uploads.max_scan_bytes', 2_097_152);

        if ($path === false) {
            return null;
        }

        try {
            if (in_array($extension, ['txt', 'csv', 'json', 'md', 'log'], true)) {
                $contents = file_get_contents($path);

                return is_string($contents) ? mb_substr($contents, 0, $maxBytes) : null;
            }

            if ($extension === 'docx') {
                $zip = new ZipArchive;

                if ($zip->open($path) !== true) {
                    return null;
                }

                $xml = $zip->getFromName('word/document.xml');
                $zip->close();

                return is_string($xml) ? mb_substr(strip_tags($xml), 0, $maxBytes) : null;
            }

            if ($extension === 'xlsx') {
                $reader = new Reader;
                $parts = [];
                $length = 0;

                try {
                    $reader->open($path);

                    foreach ($reader->getSheetIterator() as $sheet) {
                        foreach ($sheet->getRowIterator() as $row) {
                            foreach ($row->getCells() as $cell) {
                                $value = $cell->getValue();

                                if ($value instanceof DateTimeInterface) {
                                    $value = $value->format('Y-m-d');
                                } elseif (! is_scalar($value) && $value !== null) {
                                    continue;
                                }

                                $part = trim((string) $value);

                                if ($part === '') {
                                    continue;
                                }

                                $parts[] = $part;
                                $length += mb_strlen($part);

                                if ($length >= $maxBytes) {
                                    break 3;
                                }
                            }
                        }
                    }
                } finally {
                    $reader->close();
                }

                return implode("\n", $parts);
            }
        } catch (Throwable $exception) {
            Log::warning('DLP could not extract text from an upload.', [
                'extension' => $extension,
                'error' => $exception->getMessage(),
            ]);
        }

        return null;
    }
}
