<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_access_tutor_management_actions(): void
    {
        $student = User::factory()->student()->create();
        $tutor = User::factory()->tutor()->create();

        $this->actingAs($student)
            ->get(route('tutor.workspace'))
            ->assertForbidden();

        $this->actingAs($student)
            ->patch(route('tutor.owned-content.update', $tutor))
            ->assertForbidden();
    }

    public function test_tutor_cannot_modify_another_tutors_owned_content(): void
    {
        $tutor = User::factory()->tutor()->create();
        $otherTutor = User::factory()->tutor()->create();

        $this->actingAs($tutor)
            ->patch(route('tutor.owned-content.update', $otherTutor))
            ->assertForbidden();
    }

    public function test_tutor_can_modify_own_owned_content(): void
    {
        $tutor = User::factory()->tutor()->create();

        $this->actingAs($tutor)
            ->patch(route('tutor.owned-content.update', $tutor))
            ->assertNoContent();
    }

    public function test_tutor_can_access_tutor_workspace(): void
    {
        $tutor = User::factory()->tutor()->create();

        $this->actingAs($tutor)
            ->get(route('tutor.workspace'))
            ->assertRedirect(route('tutor.courses.index'));

        $this->actingAs($tutor)
            ->get(route('tutor.courses.index'))
            ->assertOk()
            ->assertSee('Tutor workspace');
    }

    public function test_student_cannot_access_another_students_private_profile(): void
    {
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('users.show', $otherStudent))
            ->assertForbidden();
    }

    public function test_student_can_access_own_profile_and_learning_area(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)
            ->get(route('users.show', $student))
            ->assertOk();

        $this->actingAs($student)
            ->get(route('student.learning'))
            ->assertOk()
            ->assertSee('My learning');
    }

    public function test_tutor_cannot_access_student_learning_area(): void
    {
        $tutor = User::factory()->tutor()->create();

        $this->actingAs($tutor)
            ->get(route('student.learning'))
            ->assertForbidden();
    }
}
