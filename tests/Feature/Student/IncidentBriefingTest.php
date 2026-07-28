<?php

namespace Tests\Feature\Student;

use App\Enums\AttemptStatus;
use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\EvidenceItem;
use App\Models\EvidenceType;
use App\Models\Hint;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentBriefingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_a_published_case_briefing(): void
    {
        $case = CaseModel::factory()->published()->create([
            'title' => 'API Returning 500',
            'ticket_content' => 'Checkout API started returning 500s after this morning\'s deploy.',
        ]);

        $response = $this->get("/incidents/{$case->slug}");

        $response->assertOk();
        $response->assertSee('API Returning 500');
        $response->assertSee("Checkout API started returning 500s after this morning's deploy.");
        $response->assertSee('Log In to Start');
        $response->assertDontSee('Start Investigation');
    }

    public function test_draft_case_shows_the_unavailable_state_not_a_raw_404(): void
    {
        $case = CaseModel::factory()->create(['slug' => 'still-a-draft']);

        $response = $this->get('/incidents/still-a-draft');

        $response->assertOk();
        $response->assertSee('This incident is no longer available.');
    }

    public function test_archived_case_shows_the_unavailable_state(): void
    {
        $case = CaseModel::factory()->published()->create(['slug' => 'archived-case']);
        $case->delete();

        $response = $this->get('/incidents/archived-case');

        $response->assertOk();
        $response->assertSee('This incident is no longer available.');
    }

    public function test_nonexistent_slug_is_a_plain_404(): void
    {
        $this->get('/incidents/does-not-exist')->assertNotFound();
    }

    public function test_evidence_teaser_shows_a_gentle_empty_state_when_no_evidence_exists(): void
    {
        $case = CaseModel::factory()->published()->create();

        $response = $this->get("/incidents/{$case->slug}");

        $response->assertOk();
        $response->assertSee("Evidence for this incident hasn't been attached yet.", false);
    }

    public function test_evidence_teaser_shows_type_counts_when_evidence_exists(): void
    {
        $case = CaseModel::factory()->published()->create();
        $logType = EvidenceType::factory()->create(['label' => 'Log File']);
        EvidenceItem::factory()->count(2)->create(['case_id' => $case->id, 'evidence_type_id' => $logType->id]);

        $response = $this->get("/incidents/{$case->slug}");

        $response->assertOk();
        $response->assertSee('Log File');
        $response->assertSee('2');
    }

    public function test_scoring_panel_shows_max_score_and_hint_and_reattempt_policy(): void
    {
        $case = CaseModel::factory()->published()->create(['max_score' => 42, 'allow_reattempt' => false]);
        Hint::factory()->create(['case_id' => $case->id]);

        $response = $this->get("/incidents/{$case->slug}");

        $response->assertOk();
        $response->assertSee('42');
        $response->assertSee('1 available, cost points when used');
        $response->assertSee('Not allowed');
    }

    public function test_authenticated_student_with_no_attempt_sees_start_investigation(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->published()->create();

        $response = $this->actingAs($student)->get("/incidents/{$case->slug}");

        $response->assertOk();
        $response->assertSee('Start Investigation');
    }

    public function test_starting_an_investigation_creates_an_attempt_and_redirects_to_the_workspace(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->published()->create(['max_score' => 50]);

        $response = $this->actingAs($student)->post("/incidents/{$case->slug}/start");

        $this->assertDatabaseHas('case_attempts', [
            'case_id' => $case->id,
            'user_id' => $student->id,
            'status' => AttemptStatus::InProgress->value,
            'max_possible_score' => 50,
        ]);
        $attempt = CaseAttempt::first();
        $response->assertRedirect(route('investigation.show', $attempt));
    }

    public function test_starting_again_with_an_in_progress_attempt_resumes_it_instead_of_duplicating(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->published()->create();
        $existing = CaseAttempt::factory()->create([
            'case_id' => $case->id,
            'user_id' => $student->id,
            'status' => AttemptStatus::InProgress,
        ]);

        $this->actingAs($student)->post("/incidents/{$case->slug}/start");

        $this->assertSame(1, CaseAttempt::where('case_id', $case->id)->where('user_id', $student->id)->count());
    }

    public function test_briefing_shows_resume_investigation_for_an_in_progress_attempt(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->published()->create();
        CaseAttempt::factory()->create(['case_id' => $case->id, 'user_id' => $student->id, 'status' => AttemptStatus::InProgress]);

        $response = $this->actingAs($student)->get("/incidents/{$case->slug}");

        $response->assertOk();
        $response->assertSee('Resume Investigation');
        $response->assertDontSee('Start Investigation');
    }

    public function test_briefing_offers_reattempt_and_past_report_when_allowed(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->published()->create(['allow_reattempt' => true]);
        CaseAttempt::factory()->create([
            'case_id' => $case->id,
            'user_id' => $student->id,
            'status' => AttemptStatus::Completed,
            'score_earned' => 91,
            'max_possible_score' => 100,
            'completed_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($student)->get("/incidents/{$case->slug}");

        $response->assertOk();
        $response->assertSee('Start New Investigation');
        $response->assertSee('View Past Report');
        $response->assertSeeText('91%');
    }

    public function test_briefing_shows_only_view_report_when_reattempt_is_not_allowed(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->published()->create(['allow_reattempt' => false]);
        CaseAttempt::factory()->create([
            'case_id' => $case->id,
            'user_id' => $student->id,
            'status' => AttemptStatus::Completed,
            'score_earned' => 70,
            'max_possible_score' => 100,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($student)->get("/incidents/{$case->slug}");

        $response->assertOk();
        $response->assertSee('View Past Report');
        $response->assertDontSee('Start New Investigation');
        $response->assertDontSee('Start Investigation');
    }

    public function test_starting_is_blocked_when_reattempt_is_not_allowed_and_already_completed(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->published()->create(['allow_reattempt' => false]);
        CaseAttempt::factory()->create([
            'case_id' => $case->id,
            'user_id' => $student->id,
            'status' => AttemptStatus::Completed,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($student)->post("/incidents/{$case->slug}/start");

        $response->assertRedirect(route('cases.show', $case));
        $response->assertSessionHas('error');
        $this->assertSame(1, CaseAttempt::count());
    }

    public function test_guest_cannot_start_an_investigation(): void
    {
        $case = CaseModel::factory()->published()->create();

        $this->post("/incidents/{$case->slug}/start")->assertRedirect('/login');
    }

    public function test_a_student_cannot_open_another_students_attempt(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)->get(route('investigation.show', $attempt))->assertForbidden();
        $this->actingAs($intruder)->get(route('performance-review.show', $attempt))->assertForbidden();
    }

    public function test_the_owning_student_can_open_their_own_attempt(): void
    {
        $student = User::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id]);

        $this->actingAs($student)->get(route('investigation.show', $attempt))->assertOk();
    }
}
