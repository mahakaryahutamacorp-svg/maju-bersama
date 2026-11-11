<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\PriceLevel;
use App\Models\Product;
use App\Models\ProductPrice;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\Concerns\PreparesLedger;
use Tests\TestCase;

class WholesalePriceCheckoutTest extends TestCase
{
    use PreparesLedger;

    private function productWithWholesaleLevel(Branch $branch, string $wholesalePrice = '9300.00'): Product
    {
        $product = $this->sellableProduct($branch, 10, '10000.00', '8000.00');

        ProductPrice::create([
            'product_id' => $product->id,
            'price_level_id' => PriceLevel::where('name', 'Harga Grosir')->value('id'),
            'price' => $wholesalePrice,
        ]);

        return $product;
    }

    public function test_wholesale_customer_is_charged_the_harga_grosir_price_level(): void
    {
        [$branch, , , $cashier] = $this->branchWithRoles();
        $this->seedRetailAccounts();
        $grosir = CustomerGroup::create(['name' => 'Grosir']);
        $customer = Customer::create(['branch_id' => $branch->id, 'name' => 'Toko Makmur', 'customer_group_id' => $grosir->id]);
        $product = $this->productWithWholesaleLevel($branch);

        $this->actingAs($cashier)->postJson('/pos', [
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertCreated()
            ->assertJsonPath('sale.items.0.price', '9300.00')
            ->assertJsonPath('sale.total_amount', '18600.00');
    }

    public function test_customer_type_chosen_without_a_customer_is_charged_its_price(): void
    {
        [$branch, , , $cashier] = $this->branchWithRoles();
        $this->seedRetailAccounts();
        $grosir = CustomerGroup::create(['name' => 'Grosir']);
        $product = $this->productWithWholesaleLevel($branch);

        $this->actingAs($cashier)->postJson('/pos', [
            'customer_group_id' => $grosir->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()
            ->assertJsonPath('sale.items.0.price', '9300.00');
    }

    public function test_pos_catalog_api_shows_harga_grosir_for_wholesale_customer(): void
    {
        [$branch, $master] = $this->branchWithRoles();
        $grosir = CustomerGroup::create(['name' => 'Grosir']);
        $customer = Customer::create(['branch_id' => $branch->id, 'name' => 'Toko Makmur', 'customer_group_id' => $grosir->id]);
        $product = $this->productWithWholesaleLevel($branch);
        Sanctum::actingAs($master);

        $this->getJson("/api/products/{$product->id}?customer_id={$customer->id}")
            ->assertOk()
            ->assertJsonPath('data.selling_price', '9300.00');
    }

    public function test_explicit_group_price_wins_and_unmatched_groups_keep_retail_price(): void
    {
        [$branch] = $this->branchWithRoles();
        $grosir = CustomerGroup::create(['name' => 'Grosir']);
        $petani = CustomerGroup::create(['name' => 'Petani']);
        $product = $this->productWithWholesaleLevel($branch);

        $this->assertEquals(9300, (float) $product->fresh()->getPriceForGroup($grosir->id));
        $this->assertEquals(10000, (float) $product->fresh()->getPriceForGroup($petani->id));

        ProductPrice::create(['product_id' => $product->id, 'customer_group_id' => $grosir->id, 'price' => '9000.00']);

        $this->assertEquals(9000, (float) $product->fresh()->getPriceForGroup($grosir->id));
    }

    public function test_shared_level_and_group_rows_are_detached_without_changing_prices(): void
    {
        [$branch] = $this->branchWithRoles();
        $retail = CustomerGroup::create(['name' => 'Umum/Retail']);
        $grosir = CustomerGroup::create(['name' => 'Grosir']);
        $product = $this->sellableProduct($branch, 10, '10000.00', '8000.00');
        $eceranRow = ProductPrice::create([
            'product_id' => $product->id,
            'price_level_id' => PriceLevel::where('name', 'Harga Eceran')->value('id'),
            'customer_group_id' => $retail->id,
            'price' => '10000.00',
        ]);
        $grosirRow = ProductPrice::create([
            'product_id' => $product->id,
            'price_level_id' => PriceLevel::where('name', 'Harga Grosir')->value('id'),
            'customer_group_id' => $grosir->id,
            'price' => '9300.00',
        ]);

        (require database_path('migrations/2026_11_11_000001_detach_customer_groups_from_price_level_rows.php'))->up();

        $this->assertNull($eceranRow->fresh()->customer_group_id);
        $this->assertNull($grosirRow->fresh()->customer_group_id);
        $this->assertEquals(10000, (float) $product->fresh()->getPriceForGroup($retail->id));
        $this->assertEquals(9300, (float) $product->fresh()->getPriceForGroup($grosir->id));
    }

    public function test_editing_harga_grosir_is_no_longer_overwritten_by_the_group_field(): void
    {
        [$branch, $master] = $this->branchWithRoles();
        $grosir = CustomerGroup::create(['name' => 'Grosir']);
        $eceranId = PriceLevel::where('name', 'Harga Eceran')->value('id');
        $grosirLevelId = PriceLevel::where('name', 'Harga Grosir')->value('id');
        $product = $this->productWithWholesaleLevel($branch);

        $this->actingAs($master)->put(route('backoffice.products.update', $product->id), [
            'name' => $product->name,
            'sku' => $product->sku,
            'category_id' => $product->category_id,
            'purchase_price' => 8000,
            'prices' => [$eceranId => 10000, $grosirLevelId => 9100],
            'customer_group_prices' => [$grosir->id => ''],
        ])->assertRedirect(route('backoffice.products.index'));

        $this->assertEquals(9100, (float) $product->fresh()->getPriceForGroup($grosir->id));
    }
}
