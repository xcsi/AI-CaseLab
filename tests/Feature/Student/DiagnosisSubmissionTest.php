<?php

namespace Tests\Feature\Student;

use App\Enums\AttemptStatus;
use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\Diagnosis;
use App\Models\EvidenceItem;
use App\Models\EvidenceView;
use App\Models\Hint;
use App\Models\HintUnlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiagnosisSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_cannot_view_the_report_form(): void
    {
        $attempt = CaseAttempt::factory()->create();

        $this->get(route('investigation.diagnosis.create', $attempt))->assertRedirect('/login');
    }

    public function test_a_different_student_cannot_view_the_report_form(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)->get(route('investigation.diagnosis.create', $attempt))->assertForbidden();
    }

    public function test_the_owning_student_sees_the_report_form_with_the_recap(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create([
            'user_id' => $student->id,
            'case_id' => $case->id,
            'started_at' => now()->subMinutes(42),
        ]);
        $hint = Hint::factory()->create(['case_id' => $case->id, 'score_penalty' => 5]);
        HintUnlock::create([
            'case_attempt_id' => $attempt->id,
            'hint_id' => $hint->id,
            'penalty_applied' => 5,
            'unlocked_at' => now(),
        ]);

        $response = $this->actingAs($student)->get(route('investigation.diagnosis.create', $attempt));

        $response->assertOk();
        $response->assertSee('Root Cause');
        $response->assertSee('Proposed Fix');
        $response->assertSee('Confidence');
        $response->assertSee('Evidence you relied on');
        $response->assertSee('1 (&minus;5 pts)', false);
        $response->assertSee('42 min');
    }

    public function test_evidence_items_the_student_actually_viewed_are_pre_checked(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);
        $viewedItem = EvidenceItem::factory()->create(['case_id' => $case->id]);
        $unviewedItem = EvidenceItem::factory()->create(['case_id' => $case->id]);
        EvidenceView::create(['case_attempt_id' => $attempt->id, 'evidence_item_id' => $viewedItem->id, 'view_count' => 1, 'first_viewed_at' => now()]);

        $response = $this->actingAs($student)->get(route('investigation.diagnosis.create', $attempt));

        $response->assertOk();

        $viewedInput = $this->extractEvidenceCiteInput($response->getContent(), $viewedItem->id);
        $unviewedInput = $this->extractEvidenceCiteInput($response->getContent(), $unviewedItem->id);

        $this->assertStringContainsString('checked', $viewedInput);
        $this->assertStringNotContainsString('checked', $unviewedInput);
    }

    private function extractEvidenceCiteInput(string $html, int $evidenceItemId): string
    {
        preg_match('/<input[^>]*id="evidence-cite-' . $evidenceItemId . '"[^>]*>/s', $html, $matches);

        return $matches[0] ?? '';
    }

    public function test_visiting_the_form_after_already_submitting_redirects_to_the_performance_review_placeholder(): void
    {
        $student = User::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id]);
        Diagnosis::create([
            'case_attempt_id' => $attempt->id,
            'root_cause_text' => 'Timeout on the payment gateway.',
            'proposed_fix_text' => 'Add retry with backoff.',
            'confidence_level' => 'high',
        ]);

        $response = $this->actingAs($student)->get(route('investigation.diagnosis.create', $attempt));

        $response->assertRedirect(route('performance-review.show', $attempt));
    }

    public function test_submitting_creates_a_diagnosis_and_evaluates_the_attempt(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id, 'status' => AttemptStatus::InProgress]);
        $item = EvidenceItem::factory()->create(['case_id' => $case->id]);

        $response = $this->actingAs($student)->post(route('investigation.diagnosis.store', $attempt), [
            'root_cause_text' => 'Timeout on the payment gateway.',
            'proposed_fix_text' => 'Add retry with backoff.',
            'confidence_level' => 'high',
            'cited_evidence_ids' => [$item->id],
        ]);

        $response->assertRedirect(route('performance-review.show', $attempt));

        $this->assertDatabaseHas('diagnoses', [
            'case_attempt_id' => $attempt->id,
            'root_cause_text' => 'Timeout on the payment gateway.',
            'proposed_fix_text' => 'Add retry with backoff.',
            'confidence_level' => 'high',
        ]);

        $diagnosis = Diagnosis::where('case_attempt_id', $attempt->id)->firstOrFail();
        $this->assertTrue($diagnosis->citedEvidence->pluck('id')->contains($item->id));

        // Submitting a diagnosis now runs the Evaluation Engine synchronously,
        // so the attempt reaches Completed (not just Submitted) immediately.
        $attempt->refresh();
        $this->assertEquals(AttemptStatus::Completed, $attempt->status);
        $this->assertNotNull($attempt->submitted_at);
        $this->assertNotNull($attempt->completed_at);
        $this->assertDatabaseHas('evaluations', ['case_attempt_id' => $attempt->id, 'diagnosis_id' => $diagnosis->id]);
    }

    public function test_submitting_twice_does_not_create_a_second_diagnosis(): void
    {
        $student = User::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id]);
        $payload = [
            'root_cause_text' => 'Timeout on the payment gateway.',
            'proposed_fix_text' => 'Add retry with backoff.',
            'confidence_level' => 'high',
        ];

        $this->actingAs($student)->post(route('investigation.diagnosis.store', $attempt), $payload);
        $this->actingAs($student)->post(route('investigation.diagnosis.store', $attempt), $payload)
            ->assertRedirect(route('performance-review.show', $attempt));

        $this->assertSame(1, Diagnosis::where('case_attempt_id', $attempt->id)->count());
        $this->assertSame(1, \App\Models\Evaluation::where('case_attempt_id', $attempt->id)->count());
    }

    public function test_root_cause_and_proposed_fix_are_required(): void
    {
        $student = User::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id]);

        $response = $this->actingAs($student)->post(route('investigation.diagnosis.store', $attempt), [
            'confidence_level' => 'high',
        ]);

        $response->assertSessionHasErrors(['root_cause_text', 'proposed_fix_text']);
        $this->assertDatabaseMissing('diagnoses', ['case_attempt_id' => $attempt->id]);
    }

    public function test_confidence_level_must_be_a_valid_enum_value(): void
    {
        $student = User::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id]);

        $response = $this->actingAs($student)->post(route('investigation.diagnosis.store', $attempt), [
            'root_cause_text' => 'Timeout on the payment gateway.',
            'proposed_fix_text' => 'Add retry with backoff.',
            'confidence_level' => 'extremely-confident',
        ]);

        $response->assertSessionHasErrors('confidence_level');
    }

    public function test_citing_evidence_from_another_case_is_rejected(): void
    {
        $student = User::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id]);
        $foreignItem = EvidenceItem::factory()->create();

        $response = $this->actingAs($student)->post(route('investigation.diagnosis.store', $attempt), [
            'root_cause_text' => 'Timeout on the payment gateway.',
            'proposed_fix_text' => 'Add retry with backoff.',
            'confidence_level' => 'high',
            'cited_evidence_ids' => [$foreignItem->id],
        ]);

        $response->assertSessionHasErrors('cited_evidence_ids.0');
        $this->assertDatabaseMissing('diagnoses', ['case_attempt_id' => $attempt->id]);
    }

    public function test_a_different_student_cannot_submit_on_someone_elses_attempt(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($intruder)->post(route('investigation.diagnosis.store', $attempt), [
            'root_cause_text' => 'Timeout on the payment gateway.',
            'proposed_fix_text' => 'Add retry with backoff.',
            'confidence_level' => 'high',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('diagnoses', ['case_attempt_id' => $attempt->id]);
    }

    public function test_a_guest_cannot_submit_a_diagnosis(): void
    {
        $attempt = CaseAttempt::factory()->create();

        $response = $this->post(route('investigation.diagnosis.store', $attempt), [
            'root_cause_text' => 'Timeout on the payment gateway.',
            'proposed_fix_text' => 'Add retry with backoff.',
            'confidence_level' => 'high',
        ]);

        $response->assertRedirect('/login');
    }
}
