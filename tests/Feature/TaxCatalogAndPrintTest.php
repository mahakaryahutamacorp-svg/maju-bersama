<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Category;
use App\Models\ChartOfAccount;
use App\Models\Product;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaxCatalogAndPrintTest extends TestCase
{
    public function test_product_tax_filter_returns_only_the_matching_status(): void
    {
        [$admin, $branch, $category] = $this->staff('branch_admin');
        $this->product($branch, $category, 'Pupuk Kena', 'PPN-1', true);
        $this->product($branch, $category, 'Benih Bebas', 'BEB-1', false);

        $this->actingAs($admin)
            ->get(route('backoffice.products.index', ['tax_status' => 'taxable']))
            ->assertOk()
            ->assertSee('Status Pajak')
            ->assertSee('Pupuk Kena')
            ->assertDontSee('Benih Bebas');

        $this->actingAs($admin)
            ->get(route('backoffice.products.index', ['tax_status' => 'exempt']))
            ->assertOk()
            ->assertSee('Benih Bebas')
            ->assertDontSee('Pupuk Kena');
    }

    public function test_product_tax_filter_stays_hidden_when_the_branch_is_not_taxed(): void
    {
        [$admin, $branch, $category] = $this->staff('branch_admin', taxEnabled: false);
        $this->product($branch, $category, 'Barang Biasa', 'BBS-1', true);

        $this->actingAs($admin)
            ->get(route('backoffice.products.index', ['tax_status' => 'exempt']))
            ->assertOk()
            ->assertDontSee('Status Pajak')
            ->assertSee('Barang Biasa');
    }

    public function test_print_follows_the_active_tax_filter_without_leaking_the_other_side(): void
    {
        [$admin, $fertilizer, $pesticide] = $this->mixedSale();

        $taxable = $this->actingAs($admin)->get(route('reports.sales.print', [
            'tax_scope' => 'taxable',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]));
        $taxable->assertOk();
        $taxable->assertSee('Pestisida Cetak');
        $taxable->assertSee('50.000,00');
        $taxable->assertSee('5.500,00');
        $taxable->assertDontSee('Pupuk Cetak');

        $exempt = $this->actingAs($admin)->get(route('reports.sales.print', [
            'tax_scope' => 'exempt',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]));
        $exempt->assertOk();
        $exempt->assertSee('Pupuk Cetak');
        $exempt->assertDontSee('Pestisida Cetak');
        $exempt->assertDontSee('DPP');

        $combined = $this->actingAs($admin)->get(route('reports.sales.print', [
            'tax_scope' => 'all',
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]));
        $combined->assertOk();
        $combined->assertSee('Pupuk Cetak');
        $combined->assertSee('Pestisida Cetak');
        $combined->assertSee('Subtotal non-pajak');
        $combined->assertSee('DPP');
        $combined->assertSee('PPN');
        $combined->assertSee('Total akhir');
    }

    public function test_cashier_cannot_see_tax_catalog_or_tax_reports(): void
    {
        [$cashier, $branch, $category] = $this->staff('cashier');
        $this->product($branch, $category, 'Barang Kasir', 'KSR-1', true);

        $this->actingAs($cashier)->get(route('backoffice.products.index'))
            ->assertOk()
            ->assertSee('Barang Kasir')
            ->assertDontSee('Status Pajak')
            ->assertDontSee('Non Pajak')
            ->assertDontSee('Harga Beli')
            ->assertDontSee('Tambah Produk');
        $this->actingAs($cashier)->get(route('backoffice.products.create'))->assertForbidden();
        $this->actingAs($cashier)->get(route('reports.sales'))->assertForbidden();
        $this->actingAs($cashier)->get(route('reports.sales.print'))->assertForbidden();
        $this->actingAs($cashier)->get(route('reports.sales.tax-export'))->assertForbidden();

        Sanctum::actingAs($cashier);
        $this->getJson('/api/reports/sales')->assertForbidden();
        $this->getJson('/api/products')->assertOk()->assertJsonMissingPath('data.0.is_taxable');
    }

    /**
     * @return array{0: User, 1: Branch, 2: Category}
     */
    private function staff(string $role, bool $taxEnabled = true): array
    {
        $branch = Branch::create([
            'code' => 'CBG-'.$role,
            'name' => 'Cabang '.$role,
            'tax_enabled' => $taxEnabled,
            'tax_rate' => '11.00',
        ]);
        $user = User::factory()->create([
            'branch_id' => $branch->id,
            'role' => $role,
        ]);
        $category = Category::create(['name' => 'Katalog '.$role, 'catalog' => 'pupuk']);

        return [$user, $branch, $category];
    }

    private function product(Branch $branch, Category $category, string $name, string $sku, bool $taxable): Product
    {
        return Product::create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'sku' => $sku,
            'name' => $name,
            'purchase_price' => 1000,
            'selling_price' => 2000,
            'is_taxable' => $taxable,
            'stock' => 5,
        ]);
    }

    /**
     * @return array{0: User, 1: Product, 2: Product}
     */
    private function mixedSale(): array
    {
        ChartOfAccount::insert([
            ['code' => '1110', 'name' => 'Kas', 'type' => 'asset', 'created_at' => now(), 'updated_at' => now()],
            ['code' => '1210', 'name' => 'Persediaan', 'type' => 'asset', 'created_at' => now(), 'updated_at' => now()],
            ['code' => '4110', 'name' => 'Pendapatan Penjualan', 'type' => 'revenue', 'created_at' => now(), 'updated_at' => now()],
            ['code' => '5100', 'name' => 'Harga Pokok Penjualan', 'type' => 'expense', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $branch = Branch::create([
            'code' => 'CBG-CETAK',
            'name' => 'Cabang Cetak',
            'tax_enabled' => true,
            'tax_rate' => '11.00',
        ]);
        $admin = User::factory()->create([
            'branch_id' => $branch->id,
            'role' => 'branch_admin',
        ]);
        $category = Category::create(['name' => 'Cetak', 'catalog' => 'pupuk']);
        $fertilizer = Product::create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'sku' => 'CETAK-BEBAS',
            'name' => 'Pupuk Cetak',
            'purchase_price' => 0,
            'selling_price' => 50000,
            'is_taxable' => false,
            'stock' => 5,
        ]);
        $pesticide = Product::create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'sku' => 'CETAK-PPN',
            'name' => 'Pestisida Cetak',
            'purchase_price' => 0,
            'selling_price' => 55500,
            'is_taxable' => true,
            'stock' => 5,
        ]);

        Sanctum::actingAs($admin);
        $this->postJson('/api/checkout', [
            'items' => [
                ['product_id' => $fertilizer->id, 'quantity' => 1],
                ['product_id' => $pesticide->id, 'quantity' => 1],
            ],
        ])->assertCreated();

        return [$admin, $fertilizer, $pesticide];
    }
}
