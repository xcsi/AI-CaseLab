<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\CaseModel;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->withRole(UserRole::Admin)->create();
    }

    private function instructor(): User
    {
        return User::factory()->withRole(UserRole::Instructor)->create();
    }

    public function test_guest_cannot_access_the_categories_page(): void
    {
        $this->get('/admin/categories')->assertRedirect('/login');
    }

    public function test_student_cannot_access_the_categories_page(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)->get('/admin/categories')->assertForbidden();
    }

    public function test_admin_can_view_the_categories_index(): void
    {
        Category::factory()->create(['name' => 'Backend']);

        $response = $this->actingAs($this->admin())->get('/admin/categories');

        $response->assertOk();
        $response->assertSee('Backend');
        $response->assertSee('Add Category');
    }

    public function test_instructor_can_view_but_not_manage_categories(): void
    {
        Category::factory()->create(['name' => 'Backend']);

        $response = $this->actingAs($this->instructor())->get('/admin/categories');

        $response->assertOk();
        $response->assertSee('Backend');
        $response->assertDontSee('onclick="openCreateCategoryModal()"', false);
    }

    public function test_admin_can_create_a_category(): void
    {
        $response = $this->actingAs($this->admin())->post('/admin/categories', [
            'name' => 'Cloud Infrastructure',
            'slug' => 'cloud-infrastructure',
            'description' => 'Cloud provisioning and infrastructure cases.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $this->assertDatabaseHas('categories', ['slug' => 'cloud-infrastructure', 'name' => 'Cloud Infrastructure']);
    }

    public function test_guest_cannot_create_a_category(): void
    {
        $response = $this->post('/admin/categories', [
            'name' => 'Cloud Infrastructure',
            'slug' => 'cloud-infrastructure',
        ]);

        $response->assertRedirect('/login');
        $this->assertDatabaseMissing('categories', ['slug' => 'cloud-infrastructure']);
    }

    public function test_instructor_cannot_create_a_category(): void
    {
        $response = $this->actingAs($this->instructor())->post('/admin/categories', [
            'name' => 'Cloud Infrastructure',
            'slug' => 'cloud-infrastructure',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('categories', ['slug' => 'cloud-infrastructure']);
    }

    public function test_creating_a_category_requires_a_unique_slug(): void
    {
        Category::factory()->create(['slug' => 'cloud-infrastructure']);

        $response = $this->actingAs($this->admin())->post('/admin/categories', [
            'name' => 'Cloud Infra',
            'slug' => 'cloud-infrastructure',
        ]);

        $response->assertSessionHasErrors('slug');
        $this->assertSame(1, Category::where('slug', 'cloud-infrastructure')->count());
    }

    public function test_admin_can_update_a_category(): void
    {
        $category = Category::factory()->create(['name' => 'Cloud', 'slug' => 'cloud']);

        $response = $this->actingAs($this->admin())->put("/admin/categories/{$category->id}", [
            'name' => 'Cloud Engineering',
            'slug' => 'cloud',
            'description' => 'Updated description.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Cloud Engineering']);
    }

    public function test_updating_a_category_allows_keeping_its_own_slug(): void
    {
        $category = Category::factory()->create(['slug' => 'cloud']);

        $response = $this->actingAs($this->admin())->put("/admin/categories/{$category->id}", [
            'name' => 'Cloud',
            'slug' => 'cloud',
        ]);

        $response->assertSessionDoesntHaveErrors();
    }

    public function test_admin_can_delete_an_unused_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->admin())->delete("/admin/categories/{$category->id}");

        $response->assertRedirect();
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_admin_cannot_delete_a_category_with_cases_assigned(): void
    {
        $category = Category::factory()->create();
        CaseModel::factory()->create(['category_id' => $category->id]);

        $response = $this->actingAs($this->admin())->delete("/admin/categories/{$category->id}");

        $response->assertRedirect();
        $response->assertSessionHasErrors('category');
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_guest_cannot_delete_a_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->delete("/admin/categories/{$category->id}");

        $response->assertRedirect('/login');
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_instructor_cannot_delete_a_category(): void
    {
        $category = Category::factory()->create();

        $response = $this->actingAs($this->instructor())->delete("/admin/categories/{$category->id}");

        $response->assertForbidden();
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }
}
