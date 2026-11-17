<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
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
                            {--role=auto : Role to assign. "auto" grants Super Admin only to the first user; use an empty value to skip}';

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

        $user = User::create([
            'email' => $email,
            'name' => $email,
            'password' => Hash::make($password),
            'password_reset_required' => false,
        ]);

        $roleOption = trim((string) $this->option('role'));

        if ($roleOption === 'auto') {
            // Bootstrap only: the first account is the installer admin. Later
            // accounts must be granted a role explicitly, so a hurried CLI call
            // cannot silently create another Super Admin.
            $roleName = User::query()->count() <= 1 ? 'Super Admin' : '';
        } else {
            $roleName = $roleOption;
        }

        if ($roleName !== '') {
            // The role may not exist yet on a first install (the permission
            // seeder runs after this command), so create the shell role and let
            // the seeder attach permissions to it.
            $role = Role::findOrCreate($roleName, 'web');
            $user->assignRole($role);

            $this->components->info("User created and assigned the [{$roleName}] role.");
        } else {
            $this->components->info('User Created.');
        }
    }
}
