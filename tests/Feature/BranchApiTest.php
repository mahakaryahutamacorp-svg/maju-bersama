<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BranchApiTest extends TestCase
{
    private Branch $branch;
    private Category $category;
    private User $superadmin;
    private User $branchAdmin;
    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create(['name' => 'Elektronik']);

        $this->branch = Branch::create([
            'code' => 'BR001',
            'name' => 'Branch Jakarta',
            'address' => 'Jl. Sudirman No. 1',
            'phone' => '021-1234567',
            'is_active' => true,
        ]);

        $this->superadmin = User::factory()->create([
            'branch_id' => $this->branch->id,
            'role' => 'superadmin',
            'email' => 'superadmin@example.com',
        ]);

        $this->branchAdmin = User::factory()->create([
            'branch_id' => $this->branch->id,
            'role' => 'branch_admin',
            'email' => 'branchadmin@example.com',
        ]);

        $this->cashier = User::factory()->create([
            'branch_id' => $this->branch->id,
            'role' => 'cashier',
            'email' => 'cashier@example.com',
        ]);
    }

    public function test_superadmin_can_list_all_branches(): void
    {
        $branch2 = Branch::create([
            'code' => 'BR002',
            'name' => 'Branch Bandung',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->superadmin);

        $response = $this->getJson('/api/branches');

        $response->assertOk()
            ->assertJsonPath('message', 'Branches retrieved successfully.')
            ->assertJsonCount(2, 'data');
    }

    public function test_non_superadmin_cannot_list_branches(): void
    {
        Sanctum::actingAs($this->branchAdmin);

        $response = $this->getJson('/api/branches');

        $response->assertForbidden()
            ->assertJsonPath('message', 'Unauthorized. Only superadmin can access branches.');
    }

    public function test_superadmin_can_create_branch(): void
    {
        Sanctum::actingAs($this->superadmin);

        $response = $this->postJson('/api/branches', [
            'code' => 'BR003',
            'name' => 'Branch Surabaya',
            'address' => 'Jl. Pemuda No. 10',
            'phone' => '031-9876543',
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Branch created successfully.')
            ->assertJsonPath('data.code', 'BR003')
            ->assertJsonPath('data.name', 'Branch Surabaya')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('branches', [
            'code' => 'BR003',
            'name' => 'Branch Surabaya',
        ]);
    }

    public function test_non_superadmin_cannot_create_branch(): void
    {
        Sanctum::actingAs($this->branchAdmin);

        $response = $this->postJson('/api/branches', [
            'code' => 'BR004',
            'name' => 'Unauthorized Branch',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('branches', [
            'code' => 'BR004',
        ]);
    }

    public function test_superadmin_can_view_branch_details(): void
    {
        Sanctum::actingAs($this->superadmin);

        $response = $this->getJson("/api/branches/{$this->branch->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Branch retrieved successfully.')
            ->assertJsonPath('data.id', $this->branch->id)
            ->assertJsonPath('data.code', 'BR001')
            ->assertJsonPath('data.name', 'Branch Jakarta');
    }

    public function test_superadmin_can_update_branch(): void
    {
        Sanctum::actingAs($this->superadmin);

        $response = $this->putJson("/api/branches/{$this->branch->id}", [
            'name' => 'Branch Jakarta Pusat',
            'phone' => '021-9999999',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Branch updated successfully.')
            ->assertJsonPath('data.name', 'Branch Jakarta Pusat')
            ->assertJsonPath('data.phone', '021-9999999');

        $this->assertDatabaseHas('branches', [
            'id' => $this->branch->id,
            'name' => 'Branch Jakarta Pusat',
            'phone' => '021-9999999',
        ]);
    }

    public function test_non_superadmin_cannot_update_branch(): void
    {
        Sanctum::actingAs($this->branchAdmin);

        $response = $this->putJson("/api/branches/{$this->branch->id}", [
            'name' => 'Hacked Branch Name',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('branches', [
            'id' => $this->branch->id,
            'name' => 'Branch Jakarta',
        ]);
    }

    public function test_superadmin_can_delete_branch_without_users_and_products(): void
    {
        $branchToDelete = Branch::create([
            'code' => 'BR005',
            'name' => 'Branch to Delete',
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->superadmin);

        $response = $this->deleteJson("/api/branches/{$branchToDelete->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Branch deleted successfully.');

        $this->assertSoftDeleted('branches', [
            'id' => $branchToDelete->id,
        ]);
    }

    public function test_superadmin_cannot_delete_branch_with_active_users(): void
    {
        Sanctum::actingAs($this->superadmin);

        $response = $this->deleteJson("/api/branches/{$this->branch->id}");

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot delete branch. Branch has active users.');

        $this->assertDatabaseHas('branches', [
            'id' => $this->branch->id,
            'deleted_at' => null,
        ]);
    }

    public function test_superadmin_cannot_delete_branch_with_products(): void
    {
        $branchWithProducts = Branch::create([
            'code' => 'BR006',
            'name' => 'Branch with Products',
            'is_active' => true,
        ]);

        User::where('branch_id', $branchWithProducts->id)->update(['is_active' => false]);

        Product::create([
            'branch_id' => $branchWithProducts->id,
            'category_id' => $this->category->id,
            'sku' => 'TEST-001',
            'name' => 'Test Product',
            'purchase_price' => 10000,
            'selling_price' => 15000,
            'stock' => 10,
        ]);

        Sanctum::actingAs($this->superadmin);

        $response = $this->deleteJson("/api/branches/{$branchWithProducts->id}");

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot delete branch. Branch has associated products.');

        $this->assertDatabaseHas('branches', [
            'id' => $branchWithProducts->id,
            'deleted_at' => null,
        ]);
    }

    public function test_create_branch_validation_fails_without_required_fields(): void
    {
        Sanctum::actingAs($this->superadmin);

        $response = $this->postJson('/api/branches', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'name']);
    }

    public function test_create_branch_validation_fails_with_duplicate_code(): void
    {
        Sanctum::actingAs($this->superadmin);

        $response = $this->postJson('/api/branches', [
            'code' => 'BR001',
            'name' => 'Duplicate Branch',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_branch_resource_includes_counts(): void
    {
        Product::create([
            'branch_id' => $this->branch->id,
            'category_id' => $this->category->id,
            'sku' => 'PROD-001',
            'name' => 'Product 1',
            'purchase_price' => 10000,
            'selling_price' => 15000,
            'stock' => 10,
        ]);

        Sanctum::actingAs($this->superadmin);

        $response = $this->getJson("/api/branches/{$this->branch->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'code',
                    'name',
                    'products_count',
                    'users_count',
                ],
            ]);
    }

    public function test_non_superadmin_cannot_view_branch_details(): void
    {
        Sanctum::actingAs($this->cashier);

        $response = $this->getJson("/api/branches/{$this->branch->id}");

        $response->assertForbidden()
            ->assertJsonPath('message', 'Unauthorized. Only superadmin can access branches.');
    }
}
