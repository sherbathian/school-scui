<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateSuperAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_be_provisioned_from_the_console_without_printing_the_password(): void
    {
        $password = 'a-long-secret-password';

        $this->artisan('school:create-super-admin')
            ->expectsQuestion('Email address', 'admin@example.test')
            ->expectsQuestion('Full name', 'School Admin')
            ->expectsQuestion('Password (12 characters minimum)', $password)
            ->expectsQuestion('Confirm password', $password)
            ->expectsOutput('Global administrator account created for admin@example.test.')
            ->doesntExpectOutput($password)
            ->assertExitCode(0);

        $user = User::where('email', 'admin@example.test')->firstOrFail();
        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue(Hash::check($password, $user->password));
    }
}
