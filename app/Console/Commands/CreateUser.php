<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'opengrc:create-user')]
class CreateUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'opengrc:create-user
                            {email? : The email of the user}
                            {password? : The password of the user}
                            {--role=auto : admin, editor, or viewer. "auto" grants admin only to the first user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new user';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $email = $this->argument('email');
        $password = $this->argument('password');

        $roleOption = strtolower(trim((string) $this->option('role')));

        if ($roleOption === 'auto') {
            // Bootstrap only: the first account is the installer admin. Later
            // accounts default to viewer, so a hurried CLI call cannot create
            // another privileged account.
            $role = User::query()->count() === 0 ? UserRole::Admin : UserRole::Viewer;
        } else {
            $role = UserRole::tryFrom($roleOption);

            if (! $role) {
                $this->error('Role must be one of: admin, editor, viewer, auto.');

                return;
            }
        }

        User::create([
            'email' => $email,
            'name' => $email,
            'password' => Hash::make($password),
            'password_reset_required' => false,
            'role' => $role,
        ]);

        $this->components->info("User created with the [{$role->value}] role.");
    }
}
