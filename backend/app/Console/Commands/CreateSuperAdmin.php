<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateSuperAdmin extends Command
{
    protected $signature = 'school:create-super-admin';

    protected $description = 'Create a global school platform administrator';

    public function handle(): int
    {
        setPermissionsTeamId(0);
        $email = strtolower(trim((string) $this->ask('Email address')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Enter a valid email address.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            if (! $this->confirm('Promote the existing account to global administrator?')) {
                $this->info('No changes made.');

                return self::SUCCESS;
            }

            $user->forceFill(['is_super_admin' => true])->save();
            $user->syncRoles(['super-admin']);
            $this->info("Global administrator access granted to {$email}.");

            return self::SUCCESS;
        }

        $name = trim((string) $this->ask('Full name'));
        $password = (string) $this->secret('Password (12 characters minimum)');
        $confirmation = (string) $this->secret('Confirm password');

        if ($name === '' || mb_strlen($password) < 12 || ! hash_equals($password, $confirmation)) {
            $this->error('A name, a 12 character password, and a matching confirmation are required.');

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);
        $user->forceFill(['is_super_admin' => true])->save();
        $user->syncRoles(['super-admin']);

        $this->info("Global administrator account created for {$email}.");

        return self::SUCCESS;
    }
}
