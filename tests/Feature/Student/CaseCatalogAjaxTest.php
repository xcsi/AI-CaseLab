<?php

namespace Tests\Feature\Student;

use App\Models\CaseModel;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Progressive enhancement over the Assigned Incidents filters/search form:
 * an XMLHttpRequest-flagged GET to the same URL returns just the results
 * partial (no layout chrome), while a normal request still returns the full
 * page. The filtering/sorting query itself is unchanged either way.
 */
class CaseCatalogAjaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_plain_request_returns_the_full_catalog_page(): void
    {
        CaseModel::factory()->published()->create(['title' => 'DNS Resolution Failure']);

        $response = $this->get(route('cases.index'));

        $response->assertOk();
        $response->assertSee('Assigned Incidents');
        $response->assertSee('DNS Resolution Failure');
    }

    public function test_an_ajax_request_returns_only_the_results_partial(): void
    {
        CaseModel::factory()->published()->create(['title' => 'DNS Resolution Failure']);

        $response = $this->get(route('cases.index'), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $response->assertSee('DNS Resolution Failure');
        $response->assertDontSee('Assigned Incidents');
        $response->assertDontSee('id="incident-filters-form"', false);
    }

    public function test_an_ajax_request_honors_the_same_filters_as_a_plain_request(): void
    {
        $category = Category::factory()->create(['name' => 'Backend']);
        CaseModel::factory()->published()->create(['title' => 'Matches Filter', 'category_id' => $category->id]);
        CaseModel::factory()->published()->create(['title' => 'Does Not Match']);

        $response = $this->get(
            route('cases.index', ['category' => $category->id]),
            ['X-Requested-With' => 'XMLHttpRequest']
        );

        $response->assertOk();
        $response->assertSee('Matches Filter');
        $response->assertDontSee('Does Not Match');
    }

    public function test_an_ajax_request_includes_a_screen_reader_result_count(): void
    {
        CaseModel::factory()->published()->create(['title' => 'DNS Resolution Failure']);

        $response = $this->get(route('cases.index'), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertOk();
        $response->assertSee('data-incident-status', false);
        $response->assertSee('Showing 1', false);
    }
}
