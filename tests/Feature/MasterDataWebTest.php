<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MasterDataWebTest extends TestCase
{
    private Branch $branchA;
    private Branch $branchB;
    private User $masterUser;
    private User $adminA;
    private User $adminB;
    private Category $categoryPupuk;
    private Category $categoryBenih;
    private Product $productA;
    private Product $productB;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Branches
        $this->branchA = Branch::create([
            'code' => 'BDG-01',
            'name' => 'Cabang Bandung',
            'address' => 'Jl. Asia Afrika No. 1, Bandung',
            'phone' => '022-123456',
            'is_active' => true,
        ]);

        $this->branchB = Branch::create([
            'code' => 'SBY-01',
            'name' => 'Cabang Surabaya',
            'address' => 'Jl. Pemuda No. 45, Surabaya',
            'phone' => '031-654321',
            'is_active' => true,
        ]);

        // 2. Create Users with different roles
        $this->masterUser = User::factory()->create([
            'name' => 'Master Administrator',
            'email' => 'master@majubersama.online',
            'role' => 'master',
            'branch_id' => $this->branchA->id,
            'is_active' => true,
        ]);

        $this->adminA = User::factory()->create([
            'name' => 'Admin Bandung',
            'email' => 'admin.bandung@majubersama.online',
            'role' => 'admin',
            'branch_id' => $this->branchA->id,
            'is_active' => true,
        ]);

        $this->adminB = User::factory()->create([
            'name' => 'Admin Surabaya',
            'email' => 'admin.surabaya@majubersama.online',
            'role' => 'admin',
            'branch_id' => $this->branchB->id,
            'is_active' => true,
        ]);

        // 3. Create Categories
        $this->categoryPupuk = Category::create(['name' => 'Pupuk']);
        $this->categoryBenih = Category::create(['name' => 'Benih Unggul']);

        // 4. Create Products for Branch A & Branch B
        $this->productA = Product::create([
            'branch_id' => $this->branchA->id,
            'category_id' => $this->categoryPupuk->id,
            'sku' => 'BDG-PUPUK-01',
            'name' => 'Pupuk NPK Mutiara 1kg',
            'purchase_price' => 15000,
            'selling_price' => 20000,
            'stock' => 50,
        ]);

        $this->productB = Product::create([
            'branch_id' => $this->branchB->id,
            'category_id' => $this->categoryBenih->id,
            'sku' => 'SBY-BENIH-01',
            'name' => 'Benih Jagung Manis 500g',
            'purchase_price' => 25000,
            'selling_price' => 35000,
            'stock' => 30,
        ]);
    }

    // ==========================================
    // 1. PRODUCT MULTI-TENANCY & CRUD TESTS
    // ==========================================

    public function test_branch_admin_only_sees_own_branch_products(): void
    {
        $response = $this->actingAs($this->adminA)->get(route('backoffice.products.index'));

        $response->assertStatus(200);
        $response->assertSee('Pupuk NPK Mutiara 1kg');
        $response->assertDontSee('Benih Jagung Manis 500g');
    }

    public function test_branch_admin_can_create_product_with_auto_generated_sku_when_empty(): void
    {
        $response = $this->actingAs($this->adminA)->post(route('backoffice.products.store'), [
            'name' => 'Pupuk Hayati Organik',
            'sku' => '', // Empty SKU to trigger auto-generation
            'category_id' => $this->categoryPupuk->id,
            'purchase_price' => 30000,
            'selling_price' => 42000,
            'stock' => 25,
        ]);

        $response->assertRedirect(route('backoffice.products.index'));
        $response->assertSessionHas('success');

        $created = Product::withoutGlobalScopes()->where('name', 'Pupuk Hayati Organik')->first();
        $this->assertNotNull($created);
        $this->assertEquals($this->branchA->id, $created->branch_id);
        $this->assertNotEmpty($created->sku);
        $this->assertStringStartsWith('BR' . $this->branchA->id . '-', $created->sku);
    }

    public function test_branch_admin_cannot_edit_or_update_product_from_another_branch(): void
    {
        // Branch Admin A tries to edit Branch B's product -> 403
        $editResponse = $this->actingAs($this->adminA)->get(route('backoffice.products.edit', $this->productB->id));
        $editResponse->assertStatus(403);

        // Branch Admin A tries to update Branch B's product -> 403
        $updateResponse = $this->actingAs($this->adminA)->put(route('backoffice.products.update', $this->productB->id), [
            'name' => 'Hacked Product Name',
            'sku' => $this->productB->sku,
            'category_id' => $this->categoryPupuk->id,
            'purchase_price' => 1000,
            'selling_price' => 2000,
        ]);
        $updateResponse->assertStatus(403);

        // Branch Admin A tries to delete Branch B's product -> 403
        $deleteResponse = $this->actingAs($this->adminA)->delete(route('backoffice.products.destroy', $this->productB->id));
        $deleteResponse->assertStatus(403);
    }

    public function test_master_can_view_all_products_and_choose_branch_on_create(): void
    {
        $response = $this->actingAs($this->masterUser)->get(route('backoffice.products.index'));

        $response->assertStatus(200);
        $response->assertSee('Pupuk NPK Mutiara 1kg');
        $response->assertSee('Benih Jagung Manis 500g');

        // Master creates product assigned to Branch B
        $createResponse = $this->actingAs($this->masterUser)->post(route('backoffice.products.store'), [
            'name' => 'Fungisida Antracol 500g',
            'sku' => 'FNG-001',
            'category_id' => $this->categoryPupuk->id,
            'branch_id' => $this->branchB->id,
            'purchase_price' => 50000,
            'selling_price' => 68000,
            'stock' => 15,
        ]);

        $createResponse->assertRedirect(route('backoffice.products.index'));

        $product = Product::withoutGlobalScopes()->where('sku', 'FNG-001')->first();
        $this->assertNotNull($product);
        $this->assertEquals($this->branchB->id, $product->branch_id);
    }

    // ==========================================
    // 2. CATEGORY CRUD TESTS
    // ==========================================

    public function test_category_crud_lifecycle(): void
    {
        // 1. Create Category
        $response = $this->actingAs($this->adminA)->post(route('backoffice.categories.store'), [
            'name' => 'Pestisida Kimia',
        ]);
        $response->assertRedirect(route('backoffice.categories.index'));
        $this->assertDatabaseHas('categories', ['name' => 'Pestisida Kimia']);

        $category = Category::where('name', 'Pestisida Kimia')->first();

        // 2. Update Category
        $updateResponse = $this->actingAs($this->adminA)->put(route('backoffice.categories.update', $category->id), [
            'name' => 'Pestisida Organik & Kimia',
        ]);
        $updateResponse->assertRedirect(route('backoffice.categories.index'));
        $this->assertDatabaseHas('categories', ['name' => 'Pestisida Organik & Kimia']);

        // 3. Delete Category (when no products linked)
        $deleteResponse = $this->actingAs($this->adminA)->delete(route('backoffice.categories.destroy', $category->id));
        $deleteResponse->assertRedirect(route('backoffice.categories.index'));
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_category_cannot_be_deleted_if_it_has_products(): void
    {
        // $this->categoryPupuk currently has $this->productA
        $response = $this->actingAs($this->adminA)->delete(route('backoffice.categories.destroy', $this->categoryPupuk->id));

        $response->assertRedirect(route('backoffice.categories.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('categories', ['id' => $this->categoryPupuk->id]);
    }

    // ==========================================
    // 3. USER / CASHIER MANAGEMENT TESTS
    // ==========================================

    public function test_branch_admin_can_create_cashier_for_own_branch_with_bcrypt_hash(): void
    {
        $response = $this->actingAs($this->adminA)->post(route('backoffice.users.store'), [
            'name' => 'Kasir Siti Bandung',
            'email' => 'siti.kasir@majubersama.online',
            'password' => 'secret123',
            'role' => 'cashier',
            'branch_id' => $this->branchA->id,
        ]);

        $response->assertRedirect(route('backoffice.users.index'));
        $response->assertSessionHas('success');

        $user = User::where('email', 'siti.kasir@majubersama.online')->first();
        $this->assertNotNull($user);
        $this->assertEquals('cashier', $user->role);
        $this->assertEquals($this->branchA->id, $user->branch_id);
        $this->assertTrue(Hash::check('secret123', $user->password));
    }

    public function test_branch_admin_cannot_create_user_for_another_branch(): void
    {
        // Branch Admin A tries to specify Branch B -> 403 Forbidden
        $response = $this->actingAs($this->adminA)->post(route('backoffice.users.store'), [
            'name' => 'Illegal Staff',
            'email' => 'illegal@majubersama.online',
            'password' => 'password123',
            'role' => 'cashier',
            'branch_id' => $this->branchB->id,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('users', ['email' => 'illegal@majubersama.online']);
    }

    public function test_branch_admin_cannot_assign_master_role(): void
    {
        // Branch Admin tries to assign master role -> validation error
        $response = $this->actingAs($this->adminA)->post(route('backoffice.users.store'), [
            'name' => 'Elevated Admin',
            'email' => 'elevated@majubersama.online',
            'password' => 'password123',
            'role' => 'master',
            'branch_id' => $this->branchA->id,
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'elevated@majubersama.online']);
    }

    public function test_branch_admin_cannot_edit_or_delete_user_of_another_branch(): void
    {
        // Admin A tries to edit Admin B -> 403
        $editResponse = $this->actingAs($this->adminA)->get(route('backoffice.users.edit', $this->adminB->id));
        $editResponse->assertStatus(403);

        // Admin A tries to delete Admin B -> 403
        $deleteResponse = $this->actingAs($this->adminA)->delete(route('backoffice.users.destroy', $this->adminB->id));
        $deleteResponse->assertStatus(403);
    }

    public function test_master_can_create_user_for_any_branch_with_any_role(): void
    {
        $response = $this->actingAs($this->masterUser)->post(route('backoffice.users.store'), [
            'name' => 'Super Admin 2',
            'email' => 'super2@majubersama.online',
            'password' => 'supersecret',
            'role' => 'master',
            'branch_id' => $this->branchB->id,
        ]);

        $response->assertRedirect(route('backoffice.users.index'));

        $user = User::where('email', 'super2@majubersama.online')->first();
        $this->assertNotNull($user);
        $this->assertEquals('master', $user->role);
        $this->assertEquals($this->branchB->id, $user->branch_id);
    }

    // ==========================================
    // 4. BRANCH MANAGEMENT TESTS (MASTER-ONLY)
    // ==========================================

    public function test_master_can_view_and_create_branches(): void
    {
        // Master views branch list
        $indexResponse = $this->actingAs($this->masterUser)->get(route('backoffice.branches.index'));
        $indexResponse->assertStatus(200);
        $indexResponse->assertSee('Cabang Bandung');
        $indexResponse->assertSee('Cabang Surabaya');

        // Master creates new branch
        $createResponse = $this->actingAs($this->masterUser)->post(route('backoffice.branches.store'), [
            'code' => 'SMG-01',
            'name' => 'Cabang Semarang',
            'address' => 'Jl. Pandanaran No. 10, Semarang',
            'phone' => '024-7654321',
        ]);

        $createResponse->assertRedirect(route('backoffice.branches.index'));
        $this->assertDatabaseHas('branches', [
            'code' => 'SMG-01',
            'name' => 'Cabang Semarang',
        ]);
    }

    public function test_branch_admin_gets_403_forbidden_when_accessing_branches(): void
    {
        // Branch Admin A tries to access branch index -> 403
        $indexResponse = $this->actingAs($this->adminA)->get(route('backoffice.branches.index'));
        $indexResponse->assertStatus(403);

        // Branch Admin A tries to access branch create form -> 403
        $createFormResponse = $this->actingAs($this->adminA)->get(route('backoffice.branches.create'));
        $createFormResponse->assertStatus(403);

        // Branch Admin A tries to store a new branch -> 403
        $storeResponse = $this->actingAs($this->adminA)->post(route('backoffice.branches.store'), [
            'code' => 'ILLEGAL-01',
            'name' => 'Cabang Ilegal',
        ]);
        $storeResponse->assertStatus(403);
    }
}
