<?php

namespace App\Console\Commands;

use App\Models\EvidenceStream;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Create a streaming producer and print its signing secret once (ADR 0054).
 *
 * The secret is not rotated here on purpose: re-running the command for an
 * existing stream is an error, so an operator cannot silently break a producer.
 * Rotation is disable, create a new name, cut over, delete the old one.
 */
class CreateEvidenceStream extends Command
{
    protected $signature = 'opengrc:evidence:stream:create
                            {--client= : The client the stream belongs to}
                            {--name= : Stream name, for example edr-alerts}';

    protected $description = 'Create a signed evidence stream and print its secret once';

    public function handle(): int
    {
        $client = trim((string) $this->option('client'));
        $name = trim((string) $this->option('name'));

        if ($client === '' || $name === '') {
            $this->error('Pass --client and --name.');

            return self::FAILURE;
        }

        if (preg_match('/^[a-z0-9._-]{1,64}$/', $name) !== 1) {
            $this->error('The name may contain lowercase letters, digits, dot, underscore and hyphen.');

            return self::FAILURE;
        }

        if (EvidenceStream::query()->where('client_id', $client)->where('name', $name)->exists()) {
            throw new InvalidArgumentException('That stream already exists. Disable it and create a new name to rotate the secret.');
        }

        $secret = Str::random(48);

        $stream = EvidenceStream::create([
            'client_id' => $client,
            'name' => $name,
            'secret' => $secret,
            'is_active' => true,
        ]);

        $this->info('Stream created. The secret is shown once and cannot be retrieved again.');
        $this->newLine();
        $this->line($secret);
        $this->newLine();
        $this->line("Client: {$client}");
        $this->line("Stream: {$name}");
        $this->line('Endpoint: POST /api/v1/evidence/stream');
        $this->line('Signature header: X-OpenGRC-Signature: t=<unix>,v1=<hmac_sha256(t.body)>');
        $this->line('Stream id: '.$stream->getKey());

        return self::SUCCESS;
    }
}
