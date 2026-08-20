<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get(route('login'))->assertOk();
    }

    public function test_student_can_authenticate(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->post(route('login.store'), [
            'email' => $student->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($student);
        $this->assertTrue($student->fresh()->isStudent());
        $response->assertRedirect(route('student.dashboard'));
    }

    public function test_tutor_can_authenticate(): void
    {
        $tutor = User::factory()->tutor()->create();

        $response = $this->post(route('login.store'), [
            'email' => $tutor->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($tutor);
        $this->assertTrue($tutor->fresh()->isTutor());
        $this->assertSame(Role::Tutor, $tutor->fresh()->role);
        $response->assertRedirect(route('tutor.workspace'));
    }

    public function test_users_cannot_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_unauthenticated_users_cannot_access_protected_routes(): void
    {
        $tutor = User::factory()->tutor()->create();

        $this->get(route('profile.show'))->assertRedirect(route('login'));
        $this->get(route('student.dashboard'))->assertRedirect(route('login'));
        $this->get(route('student.courses'))->assertRedirect(route('login'));
        $this->get(route('tutor.workspace'))->assertRedirect(route('login'));
        $this->patch(route('tutor.owned-content.update', $tutor))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_own_profile(): void
    {
        $user = User::factory()->create(['name' => 'Glenn Student']);

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Glenn Student')
            ->assertSee($user->email);
    }
}
