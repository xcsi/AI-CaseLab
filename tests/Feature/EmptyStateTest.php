<?php

namespace Tests\Feature;

use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Design System v1, Milestone 4 — empty states render as the shared
 * icon + one-line message (+ optional action) component, not bespoke
 * per-page markup.
 */
class EmptyStateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_brand_new_student_sees_the_empty_state_component_on_the_inbox(): void
    {
        $student = User::factory()->create();

        $response = $this->actingAs($student)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('empty-state', false);
        $response->assertSee('Your first incident is waiting', false);
        $response->assertSee(route('cases.index'), false);
    }

    public function test_the_catalog_shows_the_empty_state_component_when_no_cases_are_published(): void
    {
        $response = $this->get(route('cases.index'));

        $response->assertOk();
        $response->assertSee('empty-state', false);
        $response->assertSee('New incidents are being triaged', false);
    }

    public function test_the_catalog_shows_the_empty_state_component_when_filters_match_nothing(): void
    {
        CaseModel::factory()->published()->create(['title' => 'Only Case']);

        $response = $this->get(route('cases.index', ['search' => 'no-such-incident-xyz']));

        $response->assertOk();
        $response->assertSee('empty-state', false);
        $response->assertSee('No incidents match these filters', false);
        $response->assertSee('Clear Filters', false);
    }

    public function test_the_work_history_placeholder_shows_the_empty_state_component(): void
    {
        $student = User::factory()->create();

        $response = $this->actingAs($student)->get('/work-history');

        $response->assertOk();
        $response->assertSee('empty-state', false);
        $response->assertSee('Work History is coming in a later phase of the build.', false);
    }
}
