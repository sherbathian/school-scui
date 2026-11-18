<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateSchoolUser extends Command
{
    protected $signature = 'school:create-user';

    protected $description = 'Provision a standard school account without assigning a role';

    public function handle(): int
    {
        $name = trim((string) $this->ask('Full name'));
        $email = strtolower(trim((string) $this->ask('Email address')));

        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Enter a name and a valid email address.');

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error('An account already exists for this email.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Password (12 characters minimum)');
        $confirmation = (string) $this->secret('Confirm password');

        if (mb_strlen($password) < 12 || ! hash_equals($password, $confirmation)) {
            $this->error('Use a 12 character password and enter it the same way twice.');

            return self::FAILURE;
        }

        User::create(['name' => $name, 'email' => $email, 'password' => $password]);
        $this->info("School account created for {$email}. Assign it to a school from the school workspace.");

        return self::SUCCESS;
    }
}
