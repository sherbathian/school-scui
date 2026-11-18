<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateSchoolUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_standard_school_user_can_be_provisioned_without_a_membership(): void
    {
        $password = 'teacher-password-long';

        $this->artisan('school:create-user')
            ->expectsQuestion('Full name', 'Teacher Example')
            ->expectsQuestion('Email address', 'teacher@example.test')
            ->expectsQuestion('Password (12 characters minimum)', $password)
            ->expectsQuestion('Confirm password', $password)
            ->expectsOutput('School account created for teacher@example.test. Assign it to a school from the school workspace.')
            ->doesntExpectOutput($password)
            ->assertExitCode(0);

        $user = User::where('email', 'teacher@example.test')->firstOrFail();
        $this->assertFalse($user->isSuperAdmin());
        $this->assertTrue(Hash::check($password, $user->password));
    }
}
