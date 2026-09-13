<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    private Branch $branch;
    private User $regularUser;
    private User $superadmin;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create(['code' => 'BR-1', 'name' => 'Branch 1']);

        $this->regularUser = User::factory()->create([
            'branch_id' => $this->branch->id,
            'role' => 'cashier',
            'email' => 'user@example.com',
        ]);

        $this->superadmin = User::factory()->create([
            'branch_id' => $this->branch->id,
            'role' => 'superadmin',
            'email' => 'admin@example.com',
        ]);

        $this->category = Category::create(['name' => 'Electronics']);
    }

    public function test_user_can_list_categories(): void
    {
        Category::create(['name' => 'Books']);
        Category::create(['name' => 'Clothing']);

        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson('/api/categories');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_show_category(): void
    {
        Sanctum::actingAs($this->regularUser);

        $response = $this->getJson("/api/categories/{$this->category->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $this->category->id)
            ->assertJsonPath('data.name', 'Electronics');
    }

    public function test_superadmin_can_create_category(): void
    {
        Sanctum::actingAs($this->superadmin);

        $response = $this->postJson('/api/categories', [
            'name' => 'New Category',
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Category created successfully.')
            ->assertJsonPath('data.name', 'New Category');

        $this->assertDatabaseHas('categories', [
            'name' => 'New Category',
        ]);
    }

    public function test_regular_user_cannot_create_category(): void
    {
        Sanctum::actingAs($this->regularUser);

        $response = $this->postJson('/api/categories', [
            'name' => 'New Category',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Unauthorized. Only superadmin can create categories.');

        $this->assertDatabaseMissing('categories', [
            'name' => 'New Category',
        ]);
    }

    public function test_superadmin_can_update_category(): void
    {
        Sanctum::actingAs($this->superadmin);

        $response = $this->putJson("/api/categories/{$this->category->id}", [
            'name' => 'Updated Electronics',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Category updated successfully.')
            ->assertJsonPath('data.name', 'Updated Electronics');

        $this->assertDatabaseHas('categories', [
            'id' => $this->category->id,
            'name' => 'Updated Electronics',
        ]);
    }

    public function test_regular_user_cannot_update_category(): void
    {
        Sanctum::actingAs($this->regularUser);

        $response = $this->putJson("/api/categories/{$this->category->id}", [
            'name' => 'Hacked Name',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Unauthorized. Only superadmin can update categories.');

        $this->assertDatabaseHas('categories', [
            'id' => $this->category->id,
            'name' => 'Electronics',
        ]);
    }

    public function test_superadmin_can_delete_category_without_products(): void
    {
        Sanctum::actingAs($this->superadmin);

        $response = $this->deleteJson("/api/categories/{$this->category->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Category deleted successfully.');

        $this->assertDatabaseMissing('categories', [
            'id' => $this->category->id,
        ]);
    }

    public function test_superadmin_cannot_delete_category_with_products(): void
    {
        Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'sku' => 'PROD-1',
            'name' => 'Product 1',
            'purchase_price' => 100,
            'selling_price' => 150,
            'stock' => 10,
        ]);

        Sanctum::actingAs($this->superadmin);

        $response = $this->deleteJson("/api/categories/{$this->category->id}");

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot delete category. Category has associated products.');

        $this->assertDatabaseHas('categories', [
            'id' => $this->category->id,
        ]);
    }

    public function test_regular_user_cannot_delete_category(): void
    {
        Sanctum::actingAs($this->regularUser);

        $response = $this->deleteJson("/api/categories/{$this->category->id}");

        $response->assertForbidden()
            ->assertJsonPath('message', 'Unauthorized. Only superadmin can delete categories.');

        $this->assertDatabaseHas('categories', [
            'id' => $this->category->id,
        ]);
    }

    public function test_create_category_validation_fails_without_name(): void
    {
        Sanctum::actingAs($this->superadmin);

        $response = $this->postJson('/api/categories', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_create_category_validation_fails_with_duplicate_name(): void
    {
        Sanctum::actingAs($this->superadmin);

        $response = $this->postJson('/api/categories', [
            'name' => 'Electronics',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }
}
