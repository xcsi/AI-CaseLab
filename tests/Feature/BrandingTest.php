<?php

namespace Tests\Feature;

use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the AI CaseLab favicon (reusing the existing application-logo
 * mark, not a new asset) renders consistently across every standalone
 * HTML document in the app — the six layouts each carry their own
 * <head>, so there is no single shared parent to assert this from once.
 */
class BrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_welcome_page_shows_the_favicon(): void
    {
        $this->get('/')->assertSee('rel="icon" type="image/svg+xml" href="'.asset('favicon.svg').'"', false);
    }

    public function test_the_login_page_shows_the_favicon(): void
    {
        $this->get(route('login'))->assertSee('rel="icon" type="image/svg+xml" href="'.asset('favicon.svg').'"', false);
    }

    public function test_the_student_dashboard_shows_the_favicon(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)->get(route('dashboard'))
            ->assertSee('rel="icon" type="image/svg+xml" href="'.asset('favicon.svg').'"', false);
    }

    public function test_the_admin_dashboard_shows_the_favicon(): void
    {
        $admin = User::factory()->withRole(\App\Enums\UserRole::Admin)->create();

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertSee('rel="icon" type="image/svg+xml" href="'.asset('favicon.svg').'"', false);
    }

    public function test_the_investigation_workspace_shows_the_favicon(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);

        $this->actingAs($student)->get(route('investigation.show', $attempt))
            ->assertSee('rel="icon" type="image/svg+xml" href="'.asset('favicon.svg').'"', false);
    }

    public function test_the_diagnosis_page_shows_the_favicon(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);

        $this->actingAs($student)->get(route('investigation.diagnosis.create', $attempt))
            ->assertSee('rel="icon" type="image/svg+xml" href="'.asset('favicon.svg').'"', false);
    }
}
