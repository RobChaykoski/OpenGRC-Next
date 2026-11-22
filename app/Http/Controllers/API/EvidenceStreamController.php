<?php

namespace App\Http\Controllers\API;

use App\Enums\ControlStatus;
use App\Enums\EvidenceJobStatus;
use App\Enums\EvidenceSourceType;
use App\Http\Controllers\Controller;
use App\Models\EvidenceJob;
use App\Models\EvidenceRecord;
use App\Models\EvidenceSource;
use App\Models\EvidenceStream;
use App\Services\Evidence\EvidenceIngestor;
use App\Support\Evidence\NormalisedEvidence;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Time-sensitive evidence pushed by a producer (ADR 0054).
 *
 * The body is signed with the stream's secret and a timestamp, the cursor is the
 * identity, and the payload rule is unchanged: aggregate fields only, no raw log
 * lines, no content. The endpoint is off by default.
 *
 * Rejections are counted, never stored: the body may be the sensitive thing.
 */
class EvidenceStreamController extends Controller
{
    public const EVIDENCE_TYPES = ['detection-event'];

    public function store(Request $request, EvidenceIngestor $ingestor): JsonResponse
    {
        if (! (bool) config('evidence_stream.enabled', false)) {
            abort(404);
        }

        $clientId = (string) $request->user()->currentAccessToken()->client_id;

        $validated = $request->validate([
            'id' => ['required', 'string', 'max:191'],
            'sha256' => ['required', 'string', 'size:64'],
            'evidence' => ['required', 'array'],
            'evidenceType' => ['required', 'string', Rule::in(self::EVIDENCE_TYPES)],
            'stream' => ['required', 'array'],
            'stream.name' => ['required', 'string', 'max:64'],
            'stream.cursor' => ['required', 'string', 'max:191', 'not_regex:/[\x00-\x1F\x7F]/'],
            'stream.eventTime' => ['required', 'date'],
        ]);

        $stream = EvidenceStream::query()
            ->where('client_id', $clientId)
            ->where('name', (string) $validated['stream']['name'])
            ->first();

        if (! $stream instanceof EvidenceStream || ! $stream->is_active) {
            return $this->refuse('Unknown or inactive stream.', 403);
        }

        if (! $this->signatureValid($request, $stream)) {
            $stream->markRejected('invalid_signature');

            return $this->refuse('Invalid signature.', 401);
        }

        $eventTime = CarbonImmutable::parse((string) $validated['stream']['eventTime'])->utc();
        $skew = abs($eventTime->diffInSeconds(CarbonImmutable::now()));

        if ($skew > (int) config('evidence_stream.max_event_skew_hours', 24) * 3600) {
            $stream->markRejected('event_time_skew');

            return response()->json([
                'message' => 'The event time is outside the accepted window.',
                'late' => true,
            ], 422);
        }

        $canonical = $this->canonical($validated['evidence']);
        $digest = hash('sha256', $canonical);

        if (! hash_equals((string) $validated['sha256'], $digest)) {
            $stream->markRejected('payload_hash_mismatch');

            return response()->json(['message' => 'The payload hash does not match the evidence.'], 422);
        }

        $cursor = (string) $validated['stream']['cursor'];
        $externalId = 'stream:'.$stream->name.':'.$cursor;

        $existing = EvidenceRecord::query()
            ->where('client_id', $clientId)
            ->where('external_id', $externalId)
            ->first();

        if ($existing instanceof EvidenceRecord) {
            if (($existing->payload['sha256'] ?? null) !== $validated['sha256']) {
                $stream->markRejected('cursor_reused');

                return response()->json([
                    'message' => 'This cursor was already recorded with a different payload.',
                ], 409);
            }

            return $this->accepted($existing, $stream, $cursor, 200, 'duplicate');
        }

        $payload = [
            'source' => 'stream',
            'stream' => [
                'name' => $stream->name,
                'cursor' => $cursor,
                'eventTime' => $eventTime->toIso8601ZuluString(),
            ],
            'sha256' => $validated['sha256'],
            'event' => $validated['evidence'],
            'receivedAt' => CarbonImmutable::now()->utc()->toIso8601ZuluString(),
        ];

        $source = EvidenceSource::firstOrCreate(
            ['name' => 'Stream: '.$clientId.'/'.$stream->name],
            ['type' => EvidenceSourceType::Other, 'enabled' => true],
        );

        $job = EvidenceJob::create([
            'source_id' => $source->getKey(),
            'status' => EvidenceJobStatus::Completed,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        try {
            $record = $ingestor->ingest($source, $job, new NormalisedEvidence(
                externalId: $externalId,
                evidenceType: (string) $validated['evidenceType'],
                status: ControlStatus::Unknown,
                collectedAt: $eventTime,
                payload: $payload,
                clientId: $clientId,
            ));
        } catch (QueryException $exception) {
            $record = EvidenceRecord::query()
                ->where('client_id', $clientId)
                ->where('external_id', $externalId)
                ->first();

            if (! $record instanceof EvidenceRecord) {
                throw $exception;
            }

            return $this->accepted($record, $stream, $cursor, 200, 'duplicate');
        }

        $stream->markAccepted($cursor);

        return $this->accepted($record, $stream, $cursor, 202, 'accepted');
    }

    protected function signatureValid(Request $request, EvidenceStream $stream): bool
    {
        $header = (string) $request->header('X-OpenGRC-Signature');

        if (preg_match('/^t=(\d{1,12}),v1=([a-f0-9]{64})$/', $header, $matches) !== 1) {
            return false;
        }

        $timestamp = (int) $matches[1];
        $tolerance = (int) config('evidence_stream.tolerance_seconds', 300);

        if (abs(CarbonImmutable::now()->getTimestamp() - $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $stream->secret);

        return hash_equals($expected, $matches[2]);
    }

    protected function accepted(
        EvidenceRecord $record,
        EvidenceStream $stream,
        string $cursor,
        int $status,
        string $outcome,
    ): JsonResponse {
        return response()->json([
            'status' => $outcome,
            'evidenceId' => $record->getKey(),
            'stream' => $stream->name,
            'cursor' => $cursor,
        ], $status);
    }

    protected function refuse(string $message, int $status): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function canonical(array $payload): string
    {
        $this->sortKeys($payload);

        return json_encode(
            $payload,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function sortKeys(array &$payload): void
    {
        ksort($payload);

        foreach ($payload as &$value) {
            if (is_array($value)) {
                $this->sortKeys($value);
            }
        }
    }
}
