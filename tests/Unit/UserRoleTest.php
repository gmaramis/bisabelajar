<?php

namespace Tests\Unit;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_assigns_student_role_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertSame(Role::Student, $user->role);
        $this->assertTrue($user->isStudent());
        $this->assertFalse($user->isTutor());
    }

    public function test_factory_can_assign_tutor_role(): void
    {
        $user = User::factory()->tutor()->create();

        $this->assertSame(Role::Tutor, $user->role);
        $this->assertTrue($user->isTutor());
        $this->assertFalse($user->isStudent());
    }
}
