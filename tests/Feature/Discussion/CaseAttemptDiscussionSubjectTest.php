<?php

namespace Tests\Feature\Discussion;

use App\Discussion\Subjects\CaseAttemptDiscussionSubject;
use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\EvidenceItem;
use App\Models\EvidenceType;
use App\Models\EvidenceView;
use App\Models\InvestigationNote;
use App\Models\RubricCriterion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves CaseAttemptDiscussionSubject for Phase 15 Milestone 2, per
 * docs/13-ai-discussion-engine-design.md §1.5/§4.1 — the one subject-adapter
 * implementation Version 2 ships, read-only over existing Version 1 models.
 */
class CaseAttemptDiscussionSubjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_framing_text_names_the_case(): void
    {
        $case = CaseModel::factory()->create(['title' => 'API Returning 500 on Checkout']);
        $attempt = CaseAttempt::factory()->create(['case_id' => $case->id]);

        $framing = (new CaseAttemptDiscussionSubject($attempt))->framingText();

        $this->assertStringContainsString('reviewing an incident investigation', $framing);
        $this->assertStringContainsString('API Returning 500 on Checkout', $framing);
    }

    public function test_ground_truth_context_includes_the_full_ticket_evidence_rubric_and_model_solution(): void
    {
        $case = CaseModel::factory()->create([
            'ticket_content' => 'Customers report intermittent 500s on checkout.',
            'model_solution_summary' => 'Missing timeout on the payment gateway call.',
        ]);

        // 'log' is already seeded by EvidenceTypeSeeder (via TestCase's
        // $seed = true), so it's looked up here rather than factory-created,
        // which would collide with the seeded row's unique "code".
        $logType = EvidenceType::where('code', 'log')->firstOrFail();
        EvidenceItem::factory()->create([
            'case_id' => $case->id,
            'evidence_type_id' => $logType->id,
            'title' => 'Application error log',
            'payload' => ['lines' => [['level' => 'error', 'text' => 'cURL timeout after 30s']]],
        ]);

        RubricCriterion::factory()->create([
            'case_id' => $case->id,
            'title' => 'Identifies the missing timeout',
            'weight' => 20,
            'matching_type' => 'keyword',
            'expected_data' => ['keywords' => ['timeout', 'gateway']],
        ]);

        $attempt = CaseAttempt::factory()->create(['case_id' => $case->id]);

        $groundTruth = (new CaseAttemptDiscussionSubject($attempt))->groundTruthContext();

        $this->assertStringContainsString('Customers report intermittent 500s on checkout.', $groundTruth);
        $this->assertStringContainsString('Application error log', $groundTruth);
        $this->assertStringContainsString('cURL timeout after 30s', $groundTruth);
        $this->assertStringContainsString('log', $groundTruth); // evidence type code
        $this->assertStringContainsString('Identifies the missing timeout', $groundTruth);
        $this->assertStringContainsString('20', $groundTruth); // weight
        $this->assertStringContainsString('keyword', $groundTruth); // matching type
        $this->assertStringContainsString('gateway', $groundTruth); // expected_data keyword
        $this->assertStringContainsString('Missing timeout on the payment gateway call.', $groundTruth);
    }

    public function test_ground_truth_context_handles_a_case_with_no_model_solution_recorded(): void
    {
        $case = CaseModel::factory()->create(['model_solution_summary' => null]);
        $attempt = CaseAttempt::factory()->create(['case_id' => $case->id]);

        $groundTruth = (new CaseAttemptDiscussionSubject($attempt))->groundTruthContext();

        $this->assertStringContainsString('(no model solution recorded for this case)', $groundTruth);
    }

    public function test_progress_context_lists_only_evidence_the_student_actually_viewed(): void
    {
        $case = CaseModel::factory()->create();
        $viewed = EvidenceItem::factory()->create(['case_id' => $case->id, 'title' => 'Viewed log']);
        $unviewed = EvidenceItem::factory()->create(['case_id' => $case->id, 'title' => 'Never opened snapshot']);
        $attempt = CaseAttempt::factory()->create(['case_id' => $case->id]);

        EvidenceView::create([
            'case_attempt_id' => $attempt->id,
            'evidence_item_id' => $viewed->id,
            'view_count' => 3,
            'first_viewed_at' => now(),
            'last_viewed_at' => now(),
        ]);

        $progress = (new CaseAttemptDiscussionSubject($attempt))->progressContext();

        $this->assertStringContainsString('Viewed log', $progress);
        $this->assertStringContainsString('3 time(s)', $progress);
        $this->assertStringNotContainsString('Never opened snapshot', $progress);
    }

    public function test_progress_context_reports_no_evidence_viewed_yet_when_the_attempt_is_fresh(): void
    {
        $attempt = CaseAttempt::factory()->create();

        $progress = (new CaseAttemptDiscussionSubject($attempt))->progressContext();

        $this->assertStringContainsString('(none yet)', $progress);
    }

    public function test_progress_context_includes_the_students_notebook_content(): void
    {
        $attempt = CaseAttempt::factory()->create();
        InvestigationNote::create([
            'case_attempt_id' => $attempt->id,
            'content' => 'The log shows a 502 pointing at the payments service.',
        ]);

        $progress = (new CaseAttemptDiscussionSubject($attempt))->progressContext();

        $this->assertStringContainsString('The log shows a 502 pointing at the payments service.', $progress);
    }

    public function test_progress_context_reports_no_notes_written_yet_when_there_is_no_note(): void
    {
        $attempt = CaseAttempt::factory()->create();

        $progress = (new CaseAttemptDiscussionSubject($attempt))->progressContext();

        $this->assertStringContainsString('(no notes written yet)', $progress);
    }
}
