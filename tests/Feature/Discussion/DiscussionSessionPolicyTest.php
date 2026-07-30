<?php

namespace Tests\Feature\Discussion;

use App\Enums\UserRole;
use App\Models\CaseAttempt;
use App\Models\DiscussionSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves DiscussionSessionPolicy for Phase 16 Milestone 4, per
 * docs/13-ai-discussion-engine-design.md §10/§8 — "owner + admin/instructor
 * only" for view, owner-only for participate. Tested purely through the
 * real authorization gate ($user->can()), no HTTP/routes/controllers
 * involved, per this milestone's scope.
 */
class DiscussionSessionPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_policy_is_registered_and_resolves_through_the_real_gate(): void
    {
        // Proves Laravel actually wired DiscussionSessionPolicy to
        // DiscussionSession (whether via naming-convention auto-discovery
        // or explicit registration) -- not just that the class compiles.
        [$owner, $session] = $this->ownedSession();

        $this->assertTrue($owner->can('view', $session));
    }

    public function test_the_owner_can_view_their_own_session(): void
    {
        [$owner, $session] = $this->ownedSession();

        $this->assertTrue($owner->can('view', $session));
    }

    public function test_the_owner_can_participate_in_their_own_session(): void
    {
        [$owner, $session] = $this->ownedSession();

        $this->assertTrue($owner->can('participate', $session));
    }

    public function test_a_different_student_cannot_view_someone_elses_session(): void
    {
        [, $session] = $this->ownedSession();
        $otherStudent = User::factory()->create();

        $this->assertFalse($otherStudent->can('view', $session));
    }

    public function test_a_different_student_cannot_participate_in_someone_elses_session(): void
    {
        [, $session] = $this->ownedSession();
        $otherStudent = User::factory()->create();

        $this->assertFalse($otherStudent->can('participate', $session));
    }

    public function test_an_admin_can_view_any_session(): void
    {
        [, $session] = $this->ownedSession();
        $admin = User::factory()->withRole(UserRole::Admin)->create();

        $this->assertTrue($admin->can('view', $session));
    }

    public function test_an_admin_cannot_participate_in_a_students_session(): void
    {
        [, $session] = $this->ownedSession();
        $admin = User::factory()->withRole(UserRole::Admin)->create();

        $this->assertFalse($admin->can('participate', $session));
    }

    public function test_an_instructor_can_view_any_session(): void
    {
        [, $session] = $this->ownedSession();
        $instructor = User::factory()->withRole(UserRole::Instructor)->create();

        $this->assertTrue($instructor->can('view', $session));
    }

    public function test_an_instructor_cannot_participate_in_a_students_session(): void
    {
        [, $session] = $this->ownedSession();
        $instructor = User::factory()->withRole(UserRole::Instructor)->create();

        $this->assertFalse($instructor->can('participate', $session));
    }

    /**
     * @return array{0: User, 1: DiscussionSession}
     */
    private function ownedSession(): array
    {
        $owner = User::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $owner->id]);
        $session = DiscussionSession::factory()->create([
            'discussable_type' => CaseAttempt::class,
            'discussable_id' => $attempt->id,
        ]);

        return [$owner, $session];
    }
}
