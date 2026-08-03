<?php

namespace Tests\Feature\Discussion;

use App\Discussion\Personas\InterviewerPersona;
use App\Discussion\Personas\MentorPersona;
use App\Discussion\Subjects\CaseAttemptDiscussionSubject;
use App\Discussion\Support\SystemPromptBuilder;
use App\Models\CaseAttempt;
use App\Models\CaseModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves SystemPromptBuilder for Phase 15 Milestone 3, per
 * docs/13-ai-discussion-engine-design.md §4.1 — pure composition of
 * whatever the persona and subject already supply, and (the point of this
 * test file specifically) that the Subject × Persona separation actually
 * holds in the assembled output: swapping the persona changes tone-related
 * content but never the ground truth, and vice versa.
 */
class SystemPromptBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_assembles_every_section_for_a_mentor_session(): void
    {
        $case = CaseModel::factory()->create([
            'title' => 'API Returning 500',
            'ticket_content' => 'Customers report intermittent 500s.',
            'model_solution_summary' => 'Missing timeout on the gateway call.',
        ]);
        $attempt = CaseAttempt::factory()->create(['case_id' => $case->id]);
        $subject = new CaseAttemptDiscussionSubject($attempt);

        $prompt = (new SystemPromptBuilder())->build(new MentorPersona(), $subject);

        // Role/subject framing
        $this->assertStringContainsString('reviewing an incident investigation', $prompt->text);
        $this->assertStringContainsString('API Returning 500', $prompt->text);
        // Persona directives
        $this->assertStringContainsString('Mentor Review', $prompt->text);
        $this->assertStringContainsString('encouraging', $prompt->text);
        // Ground truth
        $this->assertStringContainsString('Customers report intermittent 500s.', $prompt->text);
        $this->assertStringContainsString('Missing timeout on the gateway call.', $prompt->text);
        // Progress context
        $this->assertStringContainsString('What the student has done so far', $prompt->text);
        $this->assertStringContainsString('(none yet)', $prompt->text);
        // Output contract
        $this->assertStringContainsString('"verdict"', $prompt->text);
        $this->assertStringContainsString('"reply_text"', $prompt->text);
    }

    public function test_swapping_the_persona_changes_tone_but_never_the_ground_truth(): void
    {
        $case = CaseModel::factory()->create(['model_solution_summary' => 'Missing timeout on the gateway call.']);
        $attempt = CaseAttempt::factory()->create(['case_id' => $case->id]);
        $subject = new CaseAttemptDiscussionSubject($attempt);
        $builder = new SystemPromptBuilder();

        $mentorPrompt = $builder->build(new MentorPersona(), $subject);
        $interviewerPrompt = $builder->build(new InterviewerPersona(), $subject);

        // Ground truth is identical regardless of persona — it comes from
        // the subject alone.
        $this->assertStringContainsString('Missing timeout on the gateway call.', $mentorPrompt->text);
        $this->assertStringContainsString('Missing timeout on the gateway call.', $interviewerPrompt->text);

        // Tone/persona-specific content differs and is mutually exclusive.
        $this->assertStringContainsString('Mentor Review', $mentorPrompt->text);
        $this->assertStringNotContainsString('Mentor Review', $interviewerPrompt->text);
        $this->assertStringContainsString('Technical Interview', $interviewerPrompt->text);
        $this->assertStringNotContainsString('Technical Interview', $mentorPrompt->text);
        $this->assertStringContainsString('Do not offer hints', $interviewerPrompt->text);
        $this->assertStringNotContainsString('Do not offer hints', $mentorPrompt->text);
    }

    public function test_swapping_the_subject_changes_ground_truth_but_never_the_persona_directives(): void
    {
        $caseA = CaseModel::factory()->create(['model_solution_summary' => 'Root cause A.']);
        $caseB = CaseModel::factory()->create(['model_solution_summary' => 'Root cause B.']);
        $subjectA = new CaseAttemptDiscussionSubject(CaseAttempt::factory()->create(['case_id' => $caseA->id]));
        $subjectB = new CaseAttemptDiscussionSubject(CaseAttempt::factory()->create(['case_id' => $caseB->id]));
        $builder = new SystemPromptBuilder();
        $persona = new MentorPersona();

        $promptA = $builder->build($persona, $subjectA);
        $promptB = $builder->build($persona, $subjectB);

        $this->assertStringContainsString('Root cause A.', $promptA->text);
        $this->assertStringNotContainsString('Root cause B.', $promptA->text);
        $this->assertStringContainsString('Root cause B.', $promptB->text);
        $this->assertStringNotContainsString('Root cause A.', $promptB->text);

        // Persona directives are identical regardless of subject.
        $this->assertStringContainsString('Mentor Review', $promptA->text);
        $this->assertStringContainsString('Mentor Review', $promptB->text);
    }
}
