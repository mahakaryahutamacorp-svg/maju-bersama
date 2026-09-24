<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    private Branch $branchA;
    private Branch $branchB;
    private User $userA;
    private User $userB;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branchA = Branch::create(['code' => 'BR-A', 'name' => 'Branch A']);
        $this->branchB = Branch::create(['code' => 'BR-B', 'name' => 'Branch B']);

        $this->userA = User::factory()->create([
            'branch_id' => $this->branchA->id,
            'email' => 'userA@example.com',
        ]);

        $this->userB = User::factory()->create([
            'branch_id' => $this->branchB->id,
            'email' => 'userB@example.com',
        ]);

        $this->category = Category::create(['name' => 'Electronics']);
    }

    public function test_user_can_list_products_in_their_branch(): void
    {
        Product::create([
            'branch_id' => $this->branchA->id,
            'category_id' => $this->category->id,
            'sku' => 'PROD-A1',
            'name' => 'Product A1',
            'purchase_price' => 100,
            'selling_price' => 150,
            'stock' => 10,
        ]);

        Product::create([
            'branch_id' => $this->branchB->id,
            'category_id' => $this->category->id,
            'sku' => 'PROD-B1',
            'name' => 'Product B1',
            'purchase_price' => 200,
            'selling_price' => 250,
            'stock' => 20,
        ]);

        Sanctum::actingAs($this->userA);

        $response = $this->getJson('/api/products');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sku', 'PROD-A1')
            ->assertJsonPath('data.0.name', 'Product A1');
    }

    public function test_user_cannot_see_products_from_other_branch(): void
    {
        $productB = Product::create([
            'branch_id' => $this->branchB->id,
            'category_id' => $this->category->id,
            'sku' => 'PROD-B1',
            'name' => 'Product B1',
            'purchase_price' => 200,
            'selling_price' => 250,
            'stock' => 20,
        ]);

        Sanctum::actingAs($this->userA);

        $response = $this->getJson("/api/products/{$productB->id}");
        $response->assertNotFound();
    }

    public function test_user_can_create_product_with_auto_generated_sku(): void
    {
        Sanctum::actingAs($this->userA);

        $response = $this->postJson('/api/products', [
            'category_id' => $this->category->id,
            'name' => 'New Product',
            'purchase_price' => 100,
            'selling_price' => 150,
            'stock' => 10,
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Product created successfully.')
            ->assertJsonPath('data.name', 'New Product')
            ->assertJsonPath('data.branch_id', $this->branchA->id);

        $product = Product::where('name', 'New Product')->first();
        $this->assertStringStartsWith('BR' . $this->branchA->id . '-', $product->sku);
    }

    public function test_user_can_create_product_with_custom_sku(): void
    {
        Sanctum::actingAs($this->userA);

        $response = $this->postJson('/api/products', [
            'category_id' => $this->category->id,
            'sku' => 'CUSTOM-SKU-123',
            'name' => 'Custom SKU Product',
            'purchase_price' => 100,
            'selling_price' => 150,
            'stock' => 10,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.sku', 'CUSTOM-SKU-123');

        $this->assertDatabaseHas('products', [
            'sku' => 'CUSTOM-SKU-123',
            'branch_id' => $this->branchA->id,
        ]);
    }

    public function test_user_can_update_product_in_their_branch(): void
    {
        $product = Product::create([
            'branch_id' => $this->branchA->id,
            'category_id' => $this->category->id,
            'sku' => 'PROD-A1',
            'name' => 'Original Name',
            'purchase_price' => 100,
            'selling_price' => 150,
            'stock' => 10,
        ]);

        Sanctum::actingAs($this->userA);

        $response = $this->putJson("/api/products/{$product->id}", [
            'name' => 'Updated Name',
            'selling_price' => 200,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Product updated successfully.')
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_user_cannot_update_product_from_other_branch(): void
    {
        $productB = Product::create([
            'branch_id' => $this->branchB->id,
            'category_id' => $this->category->id,
            'sku' => 'PROD-B1',
            'name' => 'Product B1',
            'purchase_price' => 200,
            'selling_price' => 250,
            'stock' => 20,
        ]);

        Sanctum::actingAs($this->userA);

        $response = $this->putJson("/api/products/{$productB->id}", [
            'name' => 'Hacked Name',
        ]);

        $response->assertNotFound();

        $this->assertDatabaseHas('products', [
            'id' => $productB->id,
            'name' => 'Product B1',
        ]);
    }

    public function test_user_can_delete_product_in_their_branch(): void
    {
        $product = Product::create([
            'branch_id' => $this->branchA->id,
            'category_id' => $this->category->id,
            'sku' => 'PROD-A1',
            'name' => 'To Delete',
            'purchase_price' => 100,
            'selling_price' => 150,
            'stock' => 10,
        ]);

        Sanctum::actingAs($this->userA);

        $response = $this->deleteJson("/api/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Product deleted successfully.');

        $this->assertSoftDeleted('products', [
            'id' => $product->id,
        ]);
    }

    public function test_user_cannot_delete_product_from_other_branch(): void
    {
        $productB = Product::create([
            'branch_id' => $this->branchB->id,
            'category_id' => $this->category->id,
            'sku' => 'PROD-B1',
            'name' => 'Product B1',
            'purchase_price' => 200,
            'selling_price' => 250,
            'stock' => 20,
        ]);

        Sanctum::actingAs($this->userA);

        $response = $this->deleteJson("/api/products/{$productB->id}");

        $response->assertNotFound();

        $this->assertDatabaseHas('products', [
            'id' => $productB->id,
        ]);
    }

    public function test_create_product_validation_fails_without_required_fields(): void
    {
        Sanctum::actingAs($this->userA);

        $response = $this->postJson('/api/products', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id', 'name', 'purchase_price', 'selling_price']);
    }

    public function test_create_product_validation_fails_with_negative_stock(): void
    {
        Sanctum::actingAs($this->userA);

        $response = $this->postJson('/api/products', [
            'category_id' => $this->category->id,
            'name' => 'Test Product',
            'purchase_price' => 100,
            'selling_price' => 150,
            'stock' => -5,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('stock');
    }

    public function test_superadmin_can_see_products_from_all_branches(): void
    {
        $superadmin = User::factory()->create([
            'branch_id' => $this->branchA->id,
            'role' => 'superadmin',
            'email' => 'superadmin@example.com',
        ]);

        Product::create([
            'branch_id' => $this->branchA->id,
            'category_id' => $this->category->id,
            'sku' => 'PROD-A1',
            'name' => 'Product A1',
            'purchase_price' => 100,
            'selling_price' => 150,
            'stock' => 10,
        ]);

        Product::create([
            'branch_id' => $this->branchB->id,
            'category_id' => $this->category->id,
            'sku' => 'PROD-B1',
            'name' => 'Product B1',
            'purchase_price' => 200,
            'selling_price' => 250,
            'stock' => 20,
        ]);

        Sanctum::actingAs($superadmin);

        $response = $this->getJson('/api/products');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
