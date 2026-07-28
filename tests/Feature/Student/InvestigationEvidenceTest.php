<?php

namespace Tests\Feature\Student;

use App\Models\CaseAttempt;
use App\Models\CaseModel;
use App\Models\EvidenceItem;
use App\Models\EvidenceType;
use App\Models\EvidenceView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvestigationEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_evidence_explorer_groups_items_by_type_and_always_shows_the_ticket(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create(['ticket_content' => 'The checkout page is throwing a 500 error.']);
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);

        $logType = EvidenceType::where('code', 'log')->firstOrFail();
        EvidenceItem::factory()->create([
            'case_id' => $case->id,
            'evidence_type_id' => $logType->id,
            'title' => 'Application Error Log',
        ]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertSee('Support Ticket');
        $response->assertSee('The checkout page is throwing a 500 error.');
        $response->assertSee($logType->label);
        $response->assertSee('Application Error Log');
    }

    public function test_the_explorer_shows_an_empty_state_when_the_case_has_no_evidence_items(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertSee('No other evidence has been added to this incident yet.');
    }

    public function test_a_previously_viewed_evidence_item_shows_the_viewed_checkmark(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);
        $evidenceItem = EvidenceItem::factory()->create(['case_id' => $case->id]);

        EvidenceView::create([
            'case_attempt_id' => $attempt->id,
            'evidence_item_id' => $evidenceItem->id,
            'view_count' => 1,
            'first_viewed_at' => now(),
            'last_viewed_at' => now(),
        ]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertSeeText('1/1 viewed');
        $response->assertSeeInOrder([
            'evidence-viewed-check',
            $evidenceItem->title,
        ], false);
    }

    public function test_an_unviewed_evidence_item_does_not_show_the_viewed_checkmark(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);
        EvidenceItem::factory()->create(['case_id' => $case->id]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertSeeText('0/1 viewed');
    }

    public function test_the_log_renderer_shows_lines_with_level_and_timestamp(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);
        $logType = EvidenceType::where('code', 'log')->firstOrFail();
        $evidenceItem = EvidenceItem::factory()->create([
            'case_id' => $case->id,
            'evidence_type_id' => $logType->id,
            'payload' => [
                'lines' => [
                    ['level' => 'error', 'timestamp' => '2026-07-27 10:00:00', 'text' => 'NullPointerException in CheckoutService'],
                    ['level' => 'info', 'text' => 'Request received'],
                ],
            ],
        ]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertSee('NullPointerException in CheckoutService');
        $response->assertSee('2026-07-27 10:00:00');
        $response->assertSee('ERROR');
        $response->assertSee('Request received');
    }

    public function test_the_log_renderer_shows_an_empty_state_when_there_are_no_lines(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);
        $logType = EvidenceType::where('code', 'log')->firstOrFail();
        EvidenceItem::factory()->create([
            'case_id' => $case->id,
            'evidence_type_id' => $logType->id,
            'payload' => ['lines' => []],
        ]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertSee('No log content for this evidence item.');
    }

    public function test_the_code_snippet_renderer_shows_filename_language_and_highlighted_lines(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);
        $codeType = EvidenceType::where('code', 'code_snippet')->firstOrFail();
        $evidenceItem = EvidenceItem::factory()->create([
            'case_id' => $case->id,
            'evidence_type_id' => $codeType->id,
            'payload' => [
                'filename' => 'CheckoutService.php',
                'language' => 'php',
                'code' => "public function checkout()\n{\n    return null;\n}",
                'highlight_lines' => [3],
            ],
        ]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertSee('CheckoutService.php');
        $response->assertSee('php');
        $response->assertSee('return null;', false);
        $response->assertSee('evidence-code-line-highlight', false);
    }

    public function test_the_db_snapshot_renderer_shows_table_columns_and_rows(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);
        $dbType = EvidenceType::where('code', 'db_snapshot')->firstOrFail();
        $evidenceItem = EvidenceItem::factory()->create([
            'case_id' => $case->id,
            'evidence_type_id' => $dbType->id,
            'payload' => [
                'table' => 'orders',
                'columns' => [['name' => 'id', 'type' => 'bigint'], 'status'],
                'rows' => [
                    ['id' => 101, 'status' => 'failed'],
                ],
            ],
        ]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertSee('orders');
        $response->assertSee('failed');
        $response->assertSee('Show column types');
        $response->assertSee('bigint');
    }

    public function test_the_db_snapshot_renderer_shows_an_empty_state_when_there_are_no_rows(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);
        $dbType = EvidenceType::where('code', 'db_snapshot')->firstOrFail();
        EvidenceItem::factory()->create([
            'case_id' => $case->id,
            'evidence_type_id' => $dbType->id,
            'payload' => ['table' => 'orders', 'columns' => [], 'rows' => []],
        ]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertSee('No data captured for this evidence item.');
    }

    public function test_the_api_response_renderer_shows_method_endpoint_status_and_body(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);
        $apiType = EvidenceType::where('code', 'api_response')->firstOrFail();
        EvidenceItem::factory()->create([
            'case_id' => $case->id,
            'evidence_type_id' => $apiType->id,
            'payload' => [
                'method' => 'post',
                'endpoint' => '/api/checkout',
                'status' => 500,
                'response_body' => ['error' => 'Internal Server Error'],
                'request_headers' => ['Authorization' => 'Bearer token'],
            ],
        ]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertSee('POST');
        $response->assertSee('/api/checkout');
        $response->assertSee('500');
        $response->assertSee('Internal Server Error');
        $response->assertSee('Show headers');
    }

    public function test_the_screenshot_renderer_resolves_the_public_storage_url(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('evidence/checkout-error.png', 'fake-image-bytes');

        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);
        $screenshotType = EvidenceType::where('code', 'screenshot')->firstOrFail();
        EvidenceItem::factory()->create([
            'case_id' => $case->id,
            'evidence_type_id' => $screenshotType->id,
            'payload' => ['path' => 'evidence/checkout-error.png', 'caption' => 'Checkout error toast'],
        ]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertSee(Storage::disk('public')->url('evidence/checkout-error.png'), false);
        $response->assertSee('Checkout error toast');
    }

    public function test_the_screenshot_renderer_shows_an_empty_state_when_there_is_no_path(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);
        $screenshotType = EvidenceType::where('code', 'screenshot')->firstOrFail();
        EvidenceItem::factory()->create([
            'case_id' => $case->id,
            'evidence_type_id' => $screenshotType->id,
            'payload' => [],
        ]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertSee('No screenshot has been attached to this evidence item.');
    }

    public function test_the_generic_renderer_dumps_raw_payload_for_unlisted_types(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);
        $configType = EvidenceType::where('code', 'configuration')->firstOrFail();
        EvidenceItem::factory()->create([
            'case_id' => $case->id,
            'evidence_type_id' => $configType->id,
            'payload' => ['feature_flag' => 'new_checkout', 'enabled' => false],
        ]);

        $response = $this->actingAs($student)->get(route('investigation.show', $attempt));

        $response->assertOk();
        $response->assertSee('feature_flag');
        $response->assertSee('new_checkout');
    }

    public function test_recording_a_view_creates_an_evidence_view_row(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);
        $evidenceItem = EvidenceItem::factory()->create(['case_id' => $case->id]);

        $response = $this->actingAs($student)->postJson(
            route('investigation.evidence.view', [$attempt, $evidenceItem])
        );

        $response->assertOk();
        $response->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('evidence_views', [
            'case_attempt_id' => $attempt->id,
            'evidence_item_id' => $evidenceItem->id,
            'view_count' => 1,
        ]);
    }

    public function test_recording_a_view_twice_increments_the_view_count_and_keeps_first_viewed_at(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);
        $evidenceItem = EvidenceItem::factory()->create(['case_id' => $case->id]);

        $this->actingAs($student)->postJson(route('investigation.evidence.view', [$attempt, $evidenceItem]));
        $firstViewedAt = EvidenceView::first()->first_viewed_at;

        $this->actingAs($student)->postJson(route('investigation.evidence.view', [$attempt, $evidenceItem]));

        $view = EvidenceView::where('case_attempt_id', $attempt->id)
            ->where('evidence_item_id', $evidenceItem->id)
            ->first();

        $this->assertSame(2, $view->view_count);
        $this->assertTrue($firstViewedAt->equalTo($view->first_viewed_at));
    }

    public function test_recording_a_view_for_evidence_from_another_case_is_rejected(): void
    {
        $student = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $student->id, 'case_id' => $case->id]);

        $otherCase = CaseModel::factory()->create();
        $foreignEvidenceItem = EvidenceItem::factory()->create(['case_id' => $otherCase->id]);

        $response = $this->actingAs($student)->postJson(
            route('investigation.evidence.view', [$attempt, $foreignEvidenceItem])
        );

        $response->assertNotFound();
        $this->assertDatabaseMissing('evidence_views', [
            'case_attempt_id' => $attempt->id,
            'evidence_item_id' => $foreignEvidenceItem->id,
        ]);
    }

    public function test_a_different_student_cannot_record_a_view_on_someone_elses_attempt(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['user_id' => $owner->id, 'case_id' => $case->id]);
        $evidenceItem = EvidenceItem::factory()->create(['case_id' => $case->id]);

        $response = $this->actingAs($intruder)->postJson(
            route('investigation.evidence.view', [$attempt, $evidenceItem])
        );

        $response->assertForbidden();
    }

    public function test_a_guest_cannot_record_a_view(): void
    {
        $case = CaseModel::factory()->create();
        $attempt = CaseAttempt::factory()->create(['case_id' => $case->id]);
        $evidenceItem = EvidenceItem::factory()->create(['case_id' => $case->id]);

        $response = $this->postJson(route('investigation.evidence.view', [$attempt, $evidenceItem]));

        $response->assertUnauthorized();
    }
}
