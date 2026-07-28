<?php

namespace Tests\Feature\Student;

use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\Hint;
use App\Models\HintUnlock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestigationHintTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_workspace_shows_locked_hints_with_their_penalty_and_no_content(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);
        Hint::factory()->create([
            'case_id' => $case->id,
            'order_index' => 0,
            'content' => 'Check the deploy log around 09:55.',
            'score_penalty' => 5,
        ]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertSee('Hint 1');
        $response->assertSee('&minus;5 pts', false);
        $response->assertDontSee('Check the deploy log around 09:55.');
    }

    public function test_the_workspace_has_no_hints_section_when_the_case_has_no_hints(): void
    {
        $student = User::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertDontSee('hints-group', false);
    }

    public function test_a_previously_unlocked_hint_shows_its_content_on_reload(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id, 'max_possible_score' => 100]);
        $hint = Hint::factory()->create([
            'case_id' => $case->id,
            'content' => 'Check the deploy log around 09:55.',
            'score_penalty' => 5,
        ]);
        HintUnlock::create([
            'case_attempt_id' => $attempt->id,
            'hint_id' => $hint->id,
            'penalty_applied' => 5,
            'unlocked_at' => now(),
        ]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertSee('Check the deploy log around 09:55.');
    }

    public function test_unlocking_a_hint_creates_a_hint_unlock_and_deducts_the_penalty(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id, 'max_possible_score' => 100]);
        $hint = Hint::factory()->create(['case_id' => $case->id, 'score_penalty' => 5]);

        $response = $this->actingAs($student)->postJson(
            route('investigation.hints.unlock', [$attempt, $hint])
        );

        $response->assertOk();
        $response->assertJson([
            'status' => 'ok',
            'hint_id' => $hint->id,
            'content' => $hint->content,
            'penalty_applied' => '5.00',
            'max_possible_score' => 95,
        ]);

        $this->assertDatabaseHas('hint_unlocks', [
            'case_attempt_id' => $attempt->id,
            'hint_id' => $hint->id,
            'penalty_applied' => 5,
        ]);
        $this->assertEquals(95, $attempt->fresh()->max_possible_score);
    }

    public function test_unlocking_an_already_unlocked_hint_does_not_double_charge_the_penalty(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id, 'max_possible_score' => 100]);
        $hint = Hint::factory()->create(['case_id' => $case->id, 'score_penalty' => 5]);

        $this->actingAs($student)->postJson(route('investigation.hints.unlock', [$attempt, $hint]));
        $this->actingAs($student)->postJson(route('investigation.hints.unlock', [$attempt, $hint]));

        $this->assertSame(1, HintUnlock::where('case_attempt_id', $attempt->id)->where('hint_id', $hint->id)->count());
        $this->assertEquals(95, $attempt->fresh()->max_possible_score);
    }

    public function test_the_penalty_cannot_reduce_max_possible_score_below_zero(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id, 'max_possible_score' => 3]);
        $hint = Hint::factory()->create(['case_id' => $case->id, 'score_penalty' => 5]);

        $response = $this->actingAs($student)->postJson(route('investigation.hints.unlock', [$attempt, $hint]));

        $response->assertOk();
        $this->assertEquals(0, $attempt->fresh()->max_possible_score);
    }

    public function test_unlocking_a_hint_from_another_case_is_rejected(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);

        $otherCase = CaseModel::factory()->create();
        $foreignHint = Hint::factory()->create(['case_id' => $otherCase->id]);

        $response = $this->actingAs($student)->postJson(
            route('investigation.hints.unlock', [$attempt, $foreignHint])
        );

        $response->assertNotFound();
        $this->assertDatabaseMissing('hint_unlocks', [
            'case_attempt_id' => $attempt->id,
            'hint_id' => $foreignHint->id,
        ]);
    }

    public function test_a_different_student_cannot_unlock_a_hint_on_someone_elses_attempt(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $owner->id, 'case_id' => $case->id]);
        $hint = Hint::factory()->create(['case_id' => $case->id]);

        $response = $this->actingAs($intruder)->postJson(
            route('investigation.hints.unlock', [$attempt, $hint])
        );

        $response->assertForbidden();
        $this->assertDatabaseMissing('hint_unlocks', ['case_attempt_id' => $attempt->id]);
    }

    public function test_a_guest_cannot_unlock_a_hint(): void
    {
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['case_id' => $case->id]);
        $hint = Hint::factory()->create(['case_id' => $case->id]);

        $response = $this->postJson(route('investigation.hints.unlock', [$attempt, $hint]));

        $response->assertUnauthorized();
    }
}
