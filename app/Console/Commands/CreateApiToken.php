<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'opengrc:token:create')]
class CreateApiToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'opengrc:token:create
                            {email : The user the token acts as}
                            {--name=discovery : A label for the token}
                            {--client= : The discovery client id the token is bound to}
                            {--ability=* : Token abilities (repeatable)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a scoped, client-bound API token for machine ingestion';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('No user exists with that email address.');

            return self::FAILURE;
        }

        $client = trim((string) $this->option('client'));

        if ($client === '') {
            $this->error('Pass --client so the token is bound to a discovery client.');

            return self::FAILURE;
        }

        $abilities = collect($this->option('ability'))
            ->map(fn ($ability) => trim((string) $ability))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($abilities === []) {
            $abilities = ['evidence.write'];
        }

        $new = $user->createToken((string) $this->option('name'), $abilities);
        $new->accessToken->forceFill(['client_id' => $client])->save();

        $this->info('Token created. It is shown once and cannot be retrieved again.');
        $this->newLine();
        $this->line($new->plainTextToken);
        $this->newLine();
        $this->line("Client: {$client}");
        $this->line('Abilities: '.implode(', ', $abilities));

        return self::SUCCESS;
    }
}
