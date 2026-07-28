<?php

namespace Tests\Feature\Student;

use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestigationWorkspaceShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $attempt = CaseAttempt::factory()->create();

        $this->get(route('investigation.show', $attempt))->assertRedirect('/login');
    }

    public function test_the_owning_student_sees_the_workspace_shell(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create(['title' => 'API Returning 500']);
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertSee('API Returning 500');
        $response->assertSee('Evidence Explorer');
        $response->assertSee('Evidence Viewer');
        $response->assertSee('Engineering Notebook');
        $response->assertSee('Submit Diagnosis');
    }

    public function test_a_different_student_cannot_view_the_attempt(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)->get(route('investigation.show', $attempt))->assertForbidden();
    }

    public function test_the_global_shell_navigation_is_not_present(): void
    {
        $student = User::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertDontSee('Assigned Incidents');
        $response->assertDontSee('Work History');
    }

    public function test_the_submit_diagnosis_button_is_disabled(): void
    {
        $student = User::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertSee('<button type="button" class="btn btn-primary btn-sm" disabled', false);
    }

    public function test_the_exit_link_points_back_to_the_incident_briefing(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertSee(route('cases.show', $case), false);
    }

    public function test_the_timer_is_seeded_with_the_attempts_started_at(): void
    {
        $student = User::factory()->create();
        $attempt = CaseAttempt::factory()->create([
            'user_id' => $student->id,
            'started_at' => now()->subMinutes(5),
        ]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertSee('id="workspace-timer"', false);
        $response->assertSee('data-started-at="' . $attempt->started_at->toIso8601String() . '"', false);
    }

    public function test_the_evidence_viewed_counter_starts_at_zero_of_zero_for_a_fresh_attempt(): void
    {
        $student = User::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertSeeText('0/0 viewed');
    }
}
