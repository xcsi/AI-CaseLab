<?php

namespace Tests\Feature\Student;

use App\Models\CaseAttempt;
use App\Models\InvestigationNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvestigationNotebookTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_workspace_shows_the_notebook_textarea_with_the_empty_placeholder(): void
    {
        $student = User::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertSee('Engineering Notebook');
        $response->assertSee('Jot down what you notice — referenced evidence, suspicions, dead ends');
    }

    public function test_the_workspace_shows_previously_saved_notebook_content(): void
    {
        $student = User::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id]);
        InvestigationNote::create([
            'case_attempt_id' => $attempt->id,
            'content' => 'Timeout looks related to the deploy at 09:55.',
        ]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertSee('Timeout looks related to the deploy at 09:55.');
        $response->assertSee('Saved');
    }

    public function test_saving_the_notebook_creates_an_investigation_note(): void
    {
        $student = User::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id]);

        $response = $this->actingAs($student)->patchJson(
            route('investigation.notes.update', $attempt),
            ['content' => 'Checked nginx.log — upstream timeout at 10:02:04.']
        );

        $response->assertOk();
        $response->assertJson(['status' => 'ok']);
        $response->assertJsonStructure(['saved_at']);

        $this->assertDatabaseHas('investigation_notes', [
            'case_attempt_id' => $attempt->id,
            'content' => 'Checked nginx.log — upstream timeout at 10:02:04.',
        ]);
    }

    public function test_saving_the_notebook_again_updates_the_same_row(): void
    {
        $student = User::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id]);

        $this->actingAs($student)->patchJson(route('investigation.notes.update', $attempt), ['content' => 'First draft.']);
        $this->actingAs($student)->patchJson(route('investigation.notes.update', $attempt), ['content' => 'First draft, revised.']);

        $this->assertSame(1, InvestigationNote::where('case_attempt_id', $attempt->id)->count());
        $this->assertDatabaseHas('investigation_notes', [
            'case_attempt_id' => $attempt->id,
            'content' => 'First draft, revised.',
        ]);
    }

    public function test_saving_empty_content_is_allowed(): void
    {
        $student = User::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id]);

        $response = $this->actingAs($student)->patchJson(route('investigation.notes.update', $attempt), ['content' => '']);

        $response->assertOk();
    }

    public function test_saving_content_over_the_length_limit_is_rejected(): void
    {
        $student = User::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id]);

        $response = $this->actingAs($student)->patchJson(
            route('investigation.notes.update', $attempt),
            ['content' => str_repeat('a', 20001)]
        );

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('content');
    }

    public function test_a_different_student_cannot_save_notes_on_someone_elses_attempt(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($intruder)->patchJson(
            route('investigation.notes.update', $attempt),
            ['content' => 'Snooping.']
        );

        $response->assertForbidden();
        $this->assertDatabaseMissing('investigation_notes', ['case_attempt_id' => $attempt->id]);
    }

    public function test_a_guest_cannot_save_notes(): void
    {
        $attempt = CaseAttempt::factory()->create();

        $response = $this->patchJson(route('investigation.notes.update', $attempt), ['content' => 'Anonymous.']);

        $response->assertUnauthorized();
    }
}
