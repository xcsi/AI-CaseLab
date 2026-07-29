<?php

namespace Tests\Feature;

use App\Enums\ConfidenceLevel;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\EvaluationCriterionResult;
use App\Models\EvidenceType;
use App\Models\EvidenceView;
use App\Models\HintUnlock;
use App\Models\InvestigationNote;
use App\Models\RubricCriterion;
use App\Models\User;
use App\Repositories\Contracts\CaseAttemptRepositoryInterface;
use App\Repositories\Contracts\CaseRepositoryInterface;
use App\Repositories\Contracts\DiagnosisRepositoryInterface;
use App\Repositories\Contracts\EvaluationRepositoryInterface;
use App\Repositories\Contracts\EvidenceItemRepositoryInterface;
use App\Repositories\Contracts\HintRepositoryInterface;
use App\Repositories\Eloquent\EloquentCaseAttemptRepository;
use App\Repositories\Eloquent\EloquentCaseRepository;
use App\Repositories\Eloquent\EloquentDiagnosisRepository;
use App\Repositories\Eloquent\EloquentEvaluationRepository;
use App\Repositories\Eloquent\EloquentEvidenceItemRepository;
use App\Repositories\Eloquent\EloquentHintRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proof-of-wiring test for Phase 3 (per the roadmap's Phase 3 acceptance
 * criteria): builds one full case graph through the repositories and walks
 * every relationship, before any controller/service exists on top of it.
 */
class DomainGraphWiringTest extends TestCase
{
    use RefreshDatabase;

    public function test_repository_interfaces_resolve_to_their_eloquent_implementations(): void
    {
        $this->assertInstanceOf(EloquentCaseRepository::class, app(CaseRepositoryInterface::class));
        $this->assertInstanceOf(EloquentEvidenceItemRepository::class, app(EvidenceItemRepositoryInterface::class));
        $this->assertInstanceOf(EloquentCaseAttemptRepository::class, app(CaseAttemptRepositoryInterface::class));
        $this->assertInstanceOf(EloquentDiagnosisRepository::class, app(DiagnosisRepositoryInterface::class));
        $this->assertInstanceOf(EloquentEvaluationRepository::class, app(EvaluationRepositoryInterface::class));
        $this->assertInstanceOf(EloquentHintRepository::class, app(HintRepositoryInterface::class));
    }

    public function test_a_full_case_graph_can_be_built_and_traversed_through_the_repositories(): void
    {
        $category = Category::factory()->create();
        $evidenceType = EvidenceType::factory()->create();
        $admin = User::factory()->withRole(UserRole::Admin)->create();
        $student = User::factory()->create();

        $case = app(CaseRepositoryInterface::class)->create([
            'category_id' => $category->id,
            'created_by' => $admin->id,
            'title' => 'API Returning 500',
            'slug' => 'api-returning-500',
            'ticket_content' => 'Customers report intermittent 500s on checkout.',
            'difficulty' => 'medium',
            'estimated_minutes' => 30,
            'status' => 'published',
            'max_score' => 20,
        ]);

        $evidenceItem = app(EvidenceItemRepositoryInterface::class)->create([
            'case_id' => $case->id,
            'evidence_type_id' => $evidenceType->id,
            'title' => 'Nginx error log',
            'sequence_order' => 1,
            'payload' => ['text' => '502 Bad Gateway upstream timed out'],
        ]);

        $hint = app(HintRepositoryInterface::class)->create([
            'case_id' => $case->id,
            'order_index' => 1,
            'content' => 'Check the upstream timeout setting.',
            'score_penalty' => 2,
        ]);

        $rubricCriterion = RubricCriterion::create([
            'case_id' => $case->id,
            'title' => 'Identifies upstream timeout as root cause',
            'weight' => 20,
            'matching_type' => 'keyword',
            'expected_data' => ['keywords' => ['timeout', 'upstream']],
        ]);

        $attempt = app(CaseAttemptRepositoryInterface::class)->create([
            'case_id' => $case->id,
            'user_id' => $student->id,
            'case_version' => $case->version,
            'started_at' => now(),
            'max_possible_score' => $case->max_score,
        ]);

        InvestigationNote::create([
            'case_attempt_id' => $attempt->id,
            'content' => 'The log shows a 502 pointing at the payments service.',
        ]);

        EvidenceView::create([
            'case_attempt_id' => $attempt->id,
            'evidence_item_id' => $evidenceItem->id,
            'view_count' => 1,
            'first_viewed_at' => now(),
            'last_viewed_at' => now(),
        ]);

        HintUnlock::create([
            'case_attempt_id' => $attempt->id,
            'hint_id' => $hint->id,
            'penalty_applied' => $hint->score_penalty,
            'unlocked_at' => now(),
        ]);

        $diagnosis = app(DiagnosisRepositoryInterface::class)->create([
            'case_attempt_id' => $attempt->id,
            'root_cause_text' => 'The upstream payments service times out under load.',
            'proposed_fix_text' => 'Increase the upstream timeout and add a circuit breaker.',
            'confidence_level' => ConfidenceLevel::High->value,
            'submitted_at' => now(),
        ]);
        $diagnosis->citedEvidence()->attach($evidenceItem->id);

        $evaluation = app(EvaluationRepositoryInterface::class)->create([
            'case_attempt_id' => $attempt->id,
            'diagnosis_id' => $diagnosis->id,
            'total_score' => 18,
            'max_score' => 20,
            'feedback_summary' => 'Correctly identified the root cause.',
            'strategy_used' => 'KeywordMatchStrategy',
            'evaluated_at' => now(),
        ]);

        EvaluationCriterionResult::create([
            'evaluation_id' => $evaluation->id,
            'rubric_criterion_id' => $rubricCriterion->id,
            'score_awarded' => 18,
            'max_score' => 20,
            'feedback_text' => 'Matched "timeout" and "upstream".',
        ]);

        // Case -> children
        $this->assertTrue($case->evidenceItems->first()->is($evidenceItem));
        $this->assertTrue($case->hints->first()->is($hint));
        $this->assertTrue($case->rubricCriteria->first()->is($rubricCriterion));
        $this->assertTrue($case->attempts->first()->is($attempt));
        $this->assertTrue($case->author->is($admin));
        $this->assertTrue($case->category->is($category));

        // Attempt -> its 1:1s and logs
        $this->assertTrue($attempt->case->is($case));
        $this->assertTrue($attempt->user->is($student));
        $this->assertInstanceOf(InvestigationNote::class, $attempt->investigationNote);
        $this->assertTrue($attempt->diagnosis->is($diagnosis));
        $this->assertTrue($attempt->evaluation->is($evaluation));
        $this->assertCount(1, $attempt->evidenceViews);
        $this->assertCount(1, $attempt->hintUnlocks);
        $this->assertEquals($hint->score_penalty, $attempt->hintUnlocks->first()->penalty_applied);

        // Diagnosis <-> cited evidence (pivot, both directions)
        $this->assertTrue($diagnosis->citedEvidence->first()->is($evidenceItem));
        $this->assertTrue($evidenceItem->citedInDiagnoses->first()->is($diagnosis));

        // Evaluation -> criterion results -> rubric criterion
        $this->assertTrue($evaluation->criterionResults->first()->rubricCriterion->is($rubricCriterion));
        $this->assertTrue($rubricCriterion->criterionResults->first()->evaluation->is($evaluation));
    }
}
