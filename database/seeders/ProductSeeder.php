<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\CustomerGroup;
use App\Models\Inventory;
use App\Models\PriceLevel;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Master Data Katalog Produk Riil Pertanian (Pestisida, Pupuk, ZPT).
     * CRITICAL: Seluruh stok produk dan inventory di setiap cabang di-set ke 0 (Ready Go-Live).
     */
    public function run(): void
    {
        $categories = [
            'Pestisida' => Category::firstOrCreate(['name' => 'Pestisida']),
            'Pupuk' => Category::firstOrCreate(['name' => 'Pupuk']),
            'ZPT' => Category::firstOrCreate(['name' => 'ZPT']),
            'Pertanian' => Category::firstOrCreate(['name' => 'Pertanian']),
            'Elektronik' => Category::firstOrCreate(['name' => 'Elektronik']),
        ];

        $centralBranch = Branch::query()->whereNull('parent_id')->first()
            ?? Branch::query()->first();
        $branchId = $centralBranch ? $centralBranch->id : 1;

        $activeBranches = Branch::where('is_active', true)->get();
        if ($activeBranches->isEmpty()) {
            $activeBranches = Branch::all();
        }

        $levelEceran = PriceLevel::where('name', 'Harga Eceran')->first();
        $levelGrosir = PriceLevel::where('name', 'Harga Grosir')->first();
        $groupRetail = CustomerGroup::where('name', 'Umum/Retail')->first();
        $groupGrosir = CustomerGroup::where('name', 'Grosir')->first();

        $products = [
            ['sku' => 'PST-DDF1E7', 'name' => 'Gramoxone 276 SL', 'unit' => 'KG', 'category' => 'Pestisida', 'purchase_price' => 70000, 'selling_price' => 77000, 'wholesale_price' => 74900],
            ['sku' => 'PST-106F6B', 'name' => 'Roundup 486 SL', 'unit' => 'BUNGKUS', 'category' => 'Pestisida', 'purchase_price' => 18000, 'selling_price' => 25000, 'wholesale_price' => 19260],
            ['sku' => 'PST-B4F08A', 'name' => 'Amoxan 250 EC', 'unit' => 'LITER', 'category' => 'Pestisida', 'purchase_price' => 74000, 'selling_price' => 81000, 'wholesale_price' => 79180],
            ['sku' => 'PST-BD726C', 'name' => 'Furadan 3G', 'unit' => 'BUNGKUS', 'category' => 'Pestisida', 'purchase_price' => 35000, 'selling_price' => 45000, 'wholesale_price' => 37450],
            ['sku' => 'PST-D453B0', 'name' => 'Antracol 70 WP', 'unit' => 'BUNGKUS', 'category' => 'Pestisida', 'purchase_price' => 46000, 'selling_price' => 54000, 'wholesale_price' => 49220],
            ['sku' => 'PST-38C8AE', 'name' => 'Dithane M-45 80 WP', 'unit' => 'KG', 'category' => 'Pestisida', 'purchase_price' => 61000, 'selling_price' => 68000, 'wholesale_price' => 65270],
            ['sku' => 'PST-298874', 'name' => 'Score 250 EC', 'unit' => 'PCS', 'category' => 'Pestisida', 'purchase_price' => 30000, 'selling_price' => 35000, 'wholesale_price' => 32100],
            ['sku' => 'PST-54C4D1', 'name' => 'Curacron 500 EC', 'unit' => 'LITER', 'category' => 'Pestisida', 'purchase_price' => 68000, 'selling_price' => 78000, 'wholesale_price' => 72760],
            ['sku' => 'PST-07D00B', 'name' => 'Regent 50 SC', 'unit' => 'PCS', 'category' => 'Pestisida', 'purchase_price' => 69000, 'selling_price' => 77000, 'wholesale_price' => 73830],
            ['sku' => 'PST-F8C681', 'name' => 'Decis 25 EC', 'unit' => 'SACHET', 'category' => 'Pestisida', 'purchase_price' => 56000, 'selling_price' => 65000, 'wholesale_price' => 59920],
            ['sku' => 'PST-16B5B7', 'name' => 'Alika 247 ZC', 'unit' => 'BOTOL', 'category' => 'Pestisida', 'purchase_price' => 38000, 'selling_price' => 44000, 'wholesale_price' => 40660],
            ['sku' => 'PST-2EEF41', 'name' => 'Amistar Top 325 SC', 'unit' => 'LITER', 'category' => 'Pestisida', 'purchase_price' => 58000, 'selling_price' => 63000, 'wholesale_price' => 62060],
            ['sku' => 'PST-969086', 'name' => 'Bion M 1/48 WP', 'unit' => 'LITER', 'category' => 'Pestisida', 'purchase_price' => 17000, 'selling_price' => 24000, 'wholesale_price' => 18190],
            ['sku' => 'PST-506783', 'name' => 'Matador 25 EC', 'unit' => 'LITER', 'category' => 'Pestisida', 'purchase_price' => 16000, 'selling_price' => 23000, 'wholesale_price' => 17120],
            ['sku' => 'PST-5C4122', 'name' => 'Virtako 300 SC', 'unit' => 'BOTOL', 'category' => 'Pestisida', 'purchase_price' => 19000, 'selling_price' => 25000, 'wholesale_price' => 20330],
            ['sku' => 'PST-D55B58', 'name' => 'Demolish 18 EC', 'unit' => 'BOTOL', 'category' => 'Pestisida', 'purchase_price' => 53000, 'selling_price' => 58000, 'wholesale_price' => 56710],
            ['sku' => 'PST-A8F8C6', 'name' => 'Pegasus 500 SC', 'unit' => 'BOTOL', 'category' => 'Pestisida', 'purchase_price' => 16000, 'selling_price' => 21000, 'wholesale_price' => 17120],
            ['sku' => 'PST-06D1D9', 'name' => 'Prevathon 50 SC', 'unit' => 'BOTOL', 'category' => 'Pestisida', 'purchase_price' => 80000, 'selling_price' => 90000, 'wholesale_price' => 85600],
            ['sku' => 'PST-ECCC4F', 'name' => 'Darmabas 500 EC', 'unit' => 'SACHET', 'category' => 'Pestisida', 'purchase_price' => 11000, 'selling_price' => 16000, 'wholesale_price' => 11770],
            ['sku' => 'PST-616A31', 'name' => 'Spontan 400 SL', 'unit' => 'BUNGKUS', 'category' => 'Pestisida', 'purchase_price' => 23000, 'selling_price' => 28000, 'wholesale_price' => 24610],
            ['sku' => 'PST-41F797', 'name' => 'Dursban 200 EC', 'unit' => 'SACHET', 'category' => 'Pestisida', 'purchase_price' => 70000, 'selling_price' => 76000, 'wholesale_price' => 74900],
            ['sku' => 'PST-F8A301', 'name' => 'Confidor 5 WP', 'unit' => 'LITER', 'category' => 'Pestisida', 'purchase_price' => 45000, 'selling_price' => 51000, 'wholesale_price' => 48150],
            ['sku' => 'PST-8FC736', 'name' => 'Sevin 85 SP', 'unit' => 'BOTOL', 'category' => 'Pestisida', 'purchase_price' => 56000, 'selling_price' => 61000, 'wholesale_price' => 59920],
            ['sku' => 'PST-A5C32F', 'name' => 'Atonik 6.0 L', 'unit' => 'BOTOL', 'category' => 'ZPT', 'purchase_price' => 40000, 'selling_price' => 47000, 'wholesale_price' => 42800],
            ['sku' => 'PST-7C3B74', 'name' => 'Gandapan', 'unit' => 'PCS', 'category' => 'Pupuk', 'purchase_price' => 17000, 'selling_price' => 24000, 'wholesale_price' => 18190],
            ['sku' => 'PST-9DE84A', 'name' => 'Gandasil D', 'unit' => 'SACHET', 'category' => 'Pupuk', 'purchase_price' => 70000, 'selling_price' => 78000, 'wholesale_price' => 74900],
            ['sku' => 'PST-0570DF', 'name' => 'Gandasil B', 'unit' => 'BOTOL', 'category' => 'Pupuk', 'purchase_price' => 42000, 'selling_price' => 49000, 'wholesale_price' => 44940],
            ['sku' => 'PST-EFBD2B', 'name' => 'NPK Mutiara 16-16-16', 'unit' => 'BOTOL', 'category' => 'Pupuk', 'purchase_price' => 57000, 'selling_price' => 63000, 'wholesale_price' => 60990],
            ['sku' => 'PST-B23B1E', 'name' => 'Urea Non Subsidi', 'unit' => 'LITER', 'category' => 'Pupuk', 'purchase_price' => 17000, 'selling_price' => 23000, 'wholesale_price' => 18190],
            ['sku' => 'PST-42499E', 'name' => 'KCL Mahkota', 'unit' => 'SACHET', 'category' => 'Pupuk', 'purchase_price' => 48000, 'selling_price' => 57000, 'wholesale_price' => 51360],
            ['sku' => 'PST-B468A2', 'name' => 'Gromore 32-10-10', 'unit' => 'BUNGKUS', 'category' => 'Pupuk', 'purchase_price' => 12000, 'selling_price' => 22000, 'wholesale_price' => 12840],
            ['sku' => 'PST-175CE0', 'name' => 'Gromore 10-55-10', 'unit' => 'BOTOL', 'category' => 'Pupuk', 'purchase_price' => 36000, 'selling_price' => 42000, 'wholesale_price' => 38520],
            ['sku' => 'PST-34AD66', 'name' => 'Topsin M 70 WP', 'unit' => 'KG', 'category' => 'Pestisida', 'purchase_price' => 27000, 'selling_price' => 36000, 'wholesale_price' => 28890],
            ['sku' => 'PST-0610E4', 'name' => 'Bactocyn 150 AL', 'unit' => 'BOTOL', 'category' => 'Pestisida', 'purchase_price' => 74000, 'selling_price' => 81000, 'wholesale_price' => 79180],
            ['sku' => 'PST-B8DD1B', 'name' => 'Nordox 56 WP', 'unit' => 'BUNGKUS', 'category' => 'Pestisida', 'purchase_price' => 15000, 'selling_price' => 23000, 'wholesale_price' => 16050],
            ['sku' => 'PST-3D8001', 'name' => 'Kuproxat 345 SC', 'unit' => 'BOTOL', 'category' => 'Pestisida', 'purchase_price' => 47000, 'selling_price' => 57000, 'wholesale_price' => 50290],
            ['sku' => 'PST-EF1E5F', 'name' => 'Filia 525 SE', 'unit' => 'SACHET', 'category' => 'Pestisida', 'purchase_price' => 78000, 'selling_price' => 87000, 'wholesale_price' => 83460],
            ['sku' => 'PST-00E75D', 'name' => 'Nativo 75 WG', 'unit' => 'BOTOL', 'category' => 'Pestisida', 'purchase_price' => 25000, 'selling_price' => 30000, 'wholesale_price' => 26750],
            ['sku' => 'PST-222EEC', 'name' => 'Folicur 25 WP', 'unit' => 'BUNGKUS', 'category' => 'Pestisida', 'purchase_price' => 29000, 'selling_price' => 37000, 'wholesale_price' => 31030],
            ['sku' => 'PST-3A0AA8', 'name' => 'Ridomil Gold MZ', 'unit' => 'KG', 'category' => 'Pestisida', 'purchase_price' => 51000, 'selling_price' => 57000, 'wholesale_price' => 54570],
            ['sku' => 'PST-AB8A06', 'name' => 'Acrobat 50 WP', 'unit' => 'BOTOL', 'category' => 'Pestisida', 'purchase_price' => 24000, 'selling_price' => 31000, 'wholesale_price' => 25680],
            ['sku' => 'PST-44EAE3', 'name' => 'Bendas 50 WP', 'unit' => 'BUNGKUS', 'category' => 'Pestisida', 'purchase_price' => 53000, 'selling_price' => 58000, 'wholesale_price' => 56710],
            ['sku' => 'PST-E2CC22', 'name' => 'Explore 250 EC', 'unit' => 'SACHET', 'category' => 'Pestisida', 'purchase_price' => 31000, 'selling_price' => 37000, 'wholesale_price' => 33170],
            ['sku' => 'PST-6E6E15', 'name' => 'Previcur N', 'unit' => 'LITER', 'category' => 'Pestisida', 'purchase_price' => 49000, 'selling_price' => 55000, 'wholesale_price' => 52430],
            ['sku' => 'PST-AC7D8E', 'name' => 'Topsin 500 SC', 'unit' => 'SACHET', 'category' => 'Pestisida', 'purchase_price' => 53000, 'selling_price' => 59000, 'wholesale_price' => 56710],
            ['sku' => 'PST-DB3454', 'name' => 'Starban 585 EC', 'unit' => 'SACHET', 'category' => 'Pestisida', 'purchase_price' => 32000, 'selling_price' => 41000, 'wholesale_price' => 34240],
            ['sku' => 'PST-A05034', 'name' => 'Vanish 200 EC', 'unit' => 'SACHET', 'category' => 'Pestisida', 'purchase_price' => 61000, 'selling_price' => 70000, 'wholesale_price' => 65270],
            ['sku' => 'PST-2FD8EB', 'name' => 'Metindo 40 SP', 'unit' => 'LITER', 'category' => 'Pestisida', 'purchase_price' => 10000, 'selling_price' => 16000, 'wholesale_price' => 10700],
            ['sku' => 'PST-706881', 'name' => 'Dupont Lannate 25 WP', 'unit' => 'LITER', 'category' => 'Pestisida', 'purchase_price' => 27000, 'selling_price' => 33000, 'wholesale_price' => 28890],
            ['sku' => 'PST-C13602', 'name' => 'Trigard 75 WP', 'unit' => 'BUNGKUS', 'category' => 'Pestisida', 'purchase_price' => 14000, 'selling_price' => 20000, 'wholesale_price' => 14980],
            ['sku' => 'PST-5D49DC', 'name' => 'Winder 25 WP', 'unit' => 'KG', 'category' => 'Pestisida', 'purchase_price' => 66000, 'selling_price' => 72000, 'wholesale_price' => 70620],
            ['sku' => 'PST-EEA4EE', 'name' => 'Abacel 18 EC', 'unit' => 'SACHET', 'category' => 'Pestisida', 'purchase_price' => 31000, 'selling_price' => 37000, 'wholesale_price' => 33170],
            ['sku' => 'PST-657EF7', 'name' => 'Agrimec 18 EC', 'unit' => 'SACHET', 'category' => 'Pestisida', 'purchase_price' => 19000, 'selling_price' => 24000, 'wholesale_price' => 20330],
            ['sku' => 'PST-68BE73', 'name' => 'Bamex 18 EC', 'unit' => 'LITER', 'category' => 'Pestisida', 'purchase_price' => 65000, 'selling_price' => 71000, 'wholesale_price' => 69550],
            ['sku' => 'PST-D8B348', 'name' => 'Basa 500 EC', 'unit' => 'BOTOL', 'category' => 'Pestisida', 'purchase_price' => 74000, 'selling_price' => 81000, 'wholesale_price' => 79180],
            ['sku' => 'PST-B23148', 'name' => 'Sida 25 EC', 'unit' => 'PCS', 'category' => 'Pestisida', 'purchase_price' => 69000, 'selling_price' => 77000, 'wholesale_price' => 73830],
            ['sku' => 'PST-C4079B', 'name' => 'Sidametrin 50 EC', 'unit' => 'BUNGKUS', 'category' => 'Pestisida', 'purchase_price' => 63000, 'selling_price' => 71000, 'wholesale_price' => 67410],
            ['sku' => 'PST-B6F541', 'name' => 'Sidamethrin 50 EC', 'unit' => 'PCS', 'category' => 'Pestisida', 'purchase_price' => 48000, 'selling_price' => 53000, 'wholesale_price' => 51360],
            ['sku' => 'PST-C9A996', 'name' => 'Cyper 250 EC', 'unit' => 'LITER', 'category' => 'Pestisida', 'purchase_price' => 45000, 'selling_price' => 53000, 'wholesale_price' => 48150],
            ['sku' => 'PST-3CF344', 'name' => 'Rizotin 100 EC', 'unit' => 'BUNGKUS', 'category' => 'Pestisida', 'purchase_price' => 62000, 'selling_price' => 68000, 'wholesale_price' => 66340],
            ['sku' => 'PST-CE66B5', 'name' => 'Marshal 200 EC', 'unit' => 'KG', 'category' => 'Pestisida', 'purchase_price' => 79000, 'selling_price' => 86000, 'wholesale_price' => 84530],
            ['sku' => 'PST-56AD1C', 'name' => 'Mipcinta 50 WP', 'unit' => 'KG', 'category' => 'Pestisida', 'purchase_price' => 47000, 'selling_price' => 57000, 'wholesale_price' => 50290],
            ['sku' => 'PST-C9154C', 'name' => 'Bvr', 'unit' => 'BOTOL', 'category' => 'Pestisida', 'purchase_price' => 63000, 'selling_price' => 72000, 'wholesale_price' => 67410],
            ['sku' => 'PST-35EB34', 'name' => 'Coragen 164 SC', 'unit' => 'PCS', 'category' => 'Pestisida', 'purchase_price' => 22000, 'selling_price' => 32000, 'wholesale_price' => 23540],
            ['sku' => 'PST-91AF18', 'name' => 'Fenos 30 EC', 'unit' => 'KG', 'category' => 'Pestisida', 'purchase_price' => 25000, 'selling_price' => 31000, 'wholesale_price' => 26750],
            ['sku' => 'PST-A63C8C', 'name' => 'Promote 20 EC', 'unit' => 'LITER', 'category' => 'Pestisida', 'purchase_price' => 37000, 'selling_price' => 45000, 'wholesale_price' => 39590],
            ['sku' => 'PST-8E402B', 'name' => 'Destan 400 EC', 'unit' => 'KG', 'category' => 'Pestisida', 'purchase_price' => 31000, 'selling_price' => 38000, 'wholesale_price' => 33170],
            ['sku' => 'PST-5487D3', 'name' => 'Sapu Jagat', 'unit' => 'KG', 'category' => 'Pestisida', 'purchase_price' => 44000, 'selling_price' => 51000, 'wholesale_price' => 47080],
            ['sku' => 'PST-1AF83A', 'name' => 'Gempur 480 SL', 'unit' => 'LITER', 'category' => 'Pestisida', 'purchase_price' => 16000, 'selling_price' => 23000, 'wholesale_price' => 17120],
            ['sku' => 'PST-C7EBC2', 'name' => 'Kresna 480 SL', 'unit' => 'LITER', 'category' => 'Pestisida', 'purchase_price' => 16000, 'selling_price' => 23000, 'wholesale_price' => 17120],
            ['sku' => 'PST-31F2C3', 'name' => 'Basta 150 SL', 'unit' => 'BUNGKUS', 'category' => 'Pestisida', 'purchase_price' => 47000, 'selling_price' => 56000, 'wholesale_price' => 50290],
            ['sku' => 'PST-F9133D', 'name' => 'Herbatop 276 SL', 'unit' => 'SACHET', 'category' => 'Pestisida', 'purchase_price' => 45000, 'selling_price' => 54000, 'wholesale_price' => 48150],
            ['sku' => 'PST-E49531', 'name' => 'Supretox 276 SL', 'unit' => 'BOTOL', 'category' => 'Pestisida', 'purchase_price' => 19000, 'selling_price' => 24000, 'wholesale_price' => 20330],
            ['sku' => 'PST-AFFC19', 'name' => 'Noxon 297 SL', 'unit' => 'PCS', 'category' => 'Pestisida', 'purchase_price' => 21000, 'selling_price' => 28000, 'wholesale_price' => 22470],
            ['sku' => 'PST-D5E135', 'name' => 'Bio-X', 'unit' => 'BUNGKUS', 'category' => 'Pestisida', 'purchase_price' => 70000, 'selling_price' => 78000, 'wholesale_price' => 74900],
            ['sku' => 'PST-D5C83F', 'name' => 'ZPT Gibgro 10 SP', 'unit' => 'KG', 'category' => 'ZPT', 'purchase_price' => 41000, 'selling_price' => 50000, 'wholesale_price' => 43870],
            ['sku' => 'PST-4B2662', 'name' => 'Dekamon 22.43 L', 'unit' => 'KG', 'category' => 'ZPT', 'purchase_price' => 68000, 'selling_price' => 75000, 'wholesale_price' => 72760],
            ['sku' => 'PST-AF82BC', 'name' => 'Bigest 40 EC', 'unit' => 'LITER', 'category' => 'Pestisida', 'purchase_price' => 75000, 'selling_price' => 82000, 'wholesale_price' => 80250],
            ['sku' => 'PST-CFC477', 'name' => 'Ethrel 480 SL', 'unit' => 'BUNGKUS', 'category' => 'ZPT', 'purchase_price' => 48000, 'selling_price' => 56000, 'wholesale_price' => 51360],
            ['sku' => 'PST-559CC6', 'name' => 'Cepha 480 SL', 'unit' => 'BUNGKUS', 'category' => 'ZPT', 'purchase_price' => 64000, 'selling_price' => 72000, 'wholesale_price' => 68480],
            ['sku' => 'PST-120257', 'name' => 'Roundup Biosorb', 'unit' => 'LITER', 'category' => 'Pestisida', 'purchase_price' => 64000, 'selling_price' => 70000, 'wholesale_price' => 68480],
            ['sku' => 'PST-278F9B', 'name' => 'Polaris', 'unit' => 'BOTOL', 'category' => 'Pestisida', 'purchase_price' => 18000, 'selling_price' => 27000, 'wholesale_price' => 19260],
            ['sku' => 'PST-FC4438', 'name' => 'Esteron 45 WDG', 'unit' => 'BUNGKUS', 'category' => 'Pestisida', 'purchase_price' => 78000, 'selling_price' => 87000, 'wholesale_price' => 83460],
            ['sku' => 'PST-AEB7D9', 'name' => 'AM-500SC', 'unit' => '1L', 'category' => 'Pestisida', 'purchase_price' => 80000, 'selling_price' => 88000, 'wholesale_price' => 85600],
            ['sku' => 'PST-9B9757', 'name' => 'AM-500SC', 'unit' => '20L', 'category' => 'Pestisida', 'purchase_price' => 1460000, 'selling_price' => 1606000, 'wholesale_price' => 1562200],
            ['sku' => 'PST-6BC996', 'name' => 'DN-80WP 80', 'unit' => '10*1KG', 'category' => 'Pestisida', 'purchase_price' => 150000, 'selling_price' => 165000, 'wholesale_price' => 160500],
            ['sku' => 'PST-B4840F', 'name' => 'DN-80WP 80', 'unit' => '200GR', 'category' => 'Pestisida', 'purchase_price' => 28500, 'selling_price' => 31350, 'wholesale_price' => 30495],
            ['sku' => 'PST-9E1955', 'name' => 'DN-80WP 80', 'unit' => '25KG', 'category' => 'Pestisida', 'purchase_price' => 3125000, 'selling_price' => 3437500, 'wholesale_price' => 3343750],
            ['sku' => 'PST-5A3D8F', 'name' => 'FONETE', 'unit' => '20*500ML', 'category' => 'Pestisida', 'purchase_price' => 23000, 'selling_price' => 25300, 'wholesale_price' => 24610],
            ['sku' => 'PST-FED816', 'name' => 'HADES', 'unit' => '100ML', 'category' => 'Pestisida', 'purchase_price' => 25000, 'selling_price' => 27500, 'wholesale_price' => 26750],
            ['sku' => 'PST-7F6047', 'name' => 'HADES', 'unit' => '250ML', 'category' => 'Pestisida', 'purchase_price' => 55000, 'selling_price' => 60500, 'wholesale_price' => 58850],
            ['sku' => 'PST-7829EC', 'name' => 'HADES', 'unit' => '500ML', 'category' => 'Pestisida', 'purchase_price' => 95000, 'selling_price' => 104500, 'wholesale_price' => 101650],
            ['sku' => 'PST-CC0C0E', 'name' => 'OMNI GUARD', 'unit' => '100ML', 'category' => 'Pestisida', 'purchase_price' => 20000, 'selling_price' => 22000, 'wholesale_price' => 21400],
            ['sku' => 'PST-4A22C8', 'name' => 'OMNI GUARD', 'unit' => '1L', 'category' => 'Pestisida', 'purchase_price' => 175000, 'selling_price' => 192500, 'wholesale_price' => 187250],
            ['sku' => 'PST-D4B562', 'name' => 'OMNI NET', 'unit' => '200GR', 'category' => 'Pestisida', 'purchase_price' => 38000, 'selling_price' => 41800, 'wholesale_price' => 40660],
            ['sku' => 'PST-634BAA', 'name' => 'OMNI NET', 'unit' => '400GR', 'category' => 'Pestisida', 'purchase_price' => 72000, 'selling_price' => 79200, 'wholesale_price' => 77040],
            ['sku' => 'PST-0A98ED', 'name' => 'OMNI-BEST', 'unit' => '1L', 'category' => 'Pestisida', 'purchase_price' => 53000, 'selling_price' => 58300, 'wholesale_price' => 56710],
            ['sku' => 'PST-AC1D03', 'name' => 'OMNI-BEST', 'unit' => '20L', 'category' => 'Pestisida', 'purchase_price' => 49000, 'selling_price' => 53900, 'wholesale_price' => 52430],
            ['sku' => 'PST-8A6786', 'name' => 'OMNI-BEST', 'unit' => '5L', 'category' => 'Pestisida', 'purchase_price' => 50000, 'selling_price' => 55000, 'wholesale_price' => 53500],
            ['sku' => 'PST-528B04', 'name' => 'PENTA GIL-Z', 'unit' => '1L', 'category' => 'Pestisida', 'purchase_price' => 39000, 'selling_price' => 42900, 'wholesale_price' => 41730],
            ['sku' => 'PST-849FE3', 'name' => 'PENTA GIL-Z', 'unit' => '20L', 'category' => 'Pestisida', 'purchase_price' => 37000, 'selling_price' => 40700, 'wholesale_price' => 39590],
            ['sku' => 'PST-BE4364', 'name' => 'PENTA GIL-Z', 'unit' => '5L', 'category' => 'Pestisida', 'purchase_price' => 190000, 'selling_price' => 209000, 'wholesale_price' => 203300],
            ['sku' => 'PST-687ABA', 'name' => 'PENTA UP', 'unit' => '1L', 'category' => 'Pestisida', 'purchase_price' => 53000, 'selling_price' => 58300, 'wholesale_price' => 56710],
            ['sku' => 'PST-E8328A', 'name' => 'PENTA UP', 'unit' => '20L', 'category' => 'Pestisida', 'purchase_price' => 1180000, 'selling_price' => 1298000, 'wholesale_price' => 1262600],
            ['sku' => 'PST-05AE08', 'name' => 'PENTA UP', 'unit' => '5L', 'category' => 'Pestisida', 'purchase_price' => 300000, 'selling_price' => 330000, 'wholesale_price' => 321000],
            ['sku' => 'PST-3F3E49', 'name' => 'POWERSORB MAX', 'unit' => '1L', 'category' => 'Pestisida', 'purchase_price' => 67000, 'selling_price' => 73700, 'wholesale_price' => 71690],
            ['sku' => 'PST-DA629C', 'name' => 'POWERSORB MAX', 'unit' => '20L', 'category' => 'Pestisida', 'purchase_price' => 128000, 'selling_price' => 140800, 'wholesale_price' => 136960],
            ['sku' => 'PST-E3633B', 'name' => 'POWERSORB MAX', 'unit' => '5L', 'category' => 'Pestisida', 'purchase_price' => 325000, 'selling_price' => 357500, 'wholesale_price' => 347750],
            ['sku' => 'PST-2D279D', 'name' => 'PRIMA BLACK', 'unit' => '15GR', 'category' => 'Pestisida', 'purchase_price' => 2400, 'selling_price' => 2640, 'wholesale_price' => 2568],
            ['sku' => 'PST-3D610C', 'name' => 'PRIMA BOMB', 'unit' => '100ML', 'category' => 'Pestisida', 'purchase_price' => 15500, 'selling_price' => 17050, 'wholesale_price' => 16585],
            ['sku' => 'PST-72D33E', 'name' => 'PRIMA BOMB', 'unit' => '1L', 'category' => 'Pestisida', 'purchase_price' => 125000, 'selling_price' => 137500, 'wholesale_price' => 133750],
            ['sku' => 'PST-9665CA', 'name' => 'PRIMA BOMB', 'unit' => '400ML', 'category' => 'Pestisida', 'purchase_price' => 54000, 'selling_price' => 59400, 'wholesale_price' => 57780],
            ['sku' => 'PST-FAD668', 'name' => 'PRIMA CEL 18EC', 'unit' => '100ML', 'category' => 'Pestisida', 'purchase_price' => 0, 'selling_price' => 0, 'wholesale_price' => 0],
            ['sku' => 'PST-2300BE', 'name' => 'PRIMA CEL 18EC', 'unit' => '1L', 'category' => 'Pestisida', 'purchase_price' => 94500, 'selling_price' => 103950, 'wholesale_price' => 101115],
            ['sku' => 'PST-2A52BC', 'name' => 'PRIMA CEL 18EC', 'unit' => '200ML', 'category' => 'Pestisida', 'purchase_price' => 0, 'selling_price' => 0, 'wholesale_price' => 0],
            ['sku' => 'PST-7ACC63', 'name' => 'PRIMA CEL 18EC', 'unit' => '500ML', 'category' => 'Pestisida', 'purchase_price' => 78000, 'selling_price' => 85800, 'wholesale_price' => 83460],
            ['sku' => 'PST-797727', 'name' => 'PRIMA CLINK', 'unit' => '250ML', 'category' => 'Pestisida', 'purchase_price' => 95000, 'selling_price' => 104500, 'wholesale_price' => 101650],
            ['sku' => 'PST-933906', 'name' => 'PRIMA COL', 'unit' => '200GR', 'category' => 'Pestisida', 'purchase_price' => 26000, 'selling_price' => 28600, 'wholesale_price' => 27820],
            ['sku' => 'PST-92ED73', 'name' => 'PRIMA COL', 'unit' => '800GR', 'category' => 'Pestisida', 'purchase_price' => 98000, 'selling_price' => 107800, 'wholesale_price' => 104860],
            ['sku' => 'PST-94B822', 'name' => 'PRIMA FAW', 'unit' => '100ML', 'category' => 'Pestisida', 'purchase_price' => 29000, 'selling_price' => 31900, 'wholesale_price' => 31030],
            ['sku' => 'PST-0A123D', 'name' => 'PRIMA FAW', 'unit' => '250ML', 'category' => 'Pestisida', 'purchase_price' => 73000, 'selling_price' => 80300, 'wholesale_price' => 78110],
            ['sku' => 'PST-1AEE55', 'name' => 'PRIMA FAW', 'unit' => '500ML', 'category' => 'Pestisida', 'purchase_price' => 140000, 'selling_price' => 154000, 'wholesale_price' => 149800],
            ['sku' => 'PST-F3BD86', 'name' => 'PRIMA FUR', 'unit' => '1KG', 'category' => 'Pestisida', 'purchase_price' => 13000, 'selling_price' => 14300, 'wholesale_price' => 13910],
            ['sku' => 'PST-C97C75', 'name' => 'PRIMA FUR', 'unit' => '2KG', 'category' => 'Pestisida', 'purchase_price' => 23000, 'selling_price' => 25300, 'wholesale_price' => 24610],
            ['sku' => 'PST-24FCE1', 'name' => 'PRIMA GA3', 'unit' => '1GR', 'category' => 'ZPT', 'purchase_price' => 3000, 'selling_price' => 3300, 'wholesale_price' => 3210],
            ['sku' => 'PST-20DD02', 'name' => 'PRIMA HIPO', 'unit' => '1L', 'category' => 'Pestisida', 'purchase_price' => 34500, 'selling_price' => 37950, 'wholesale_price' => 36915],
            ['sku' => 'PST-5431B4', 'name' => 'PRIMA HIPO', 'unit' => '500ML', 'category' => 'Pestisida', 'purchase_price' => 25000, 'selling_price' => 27500, 'wholesale_price' => 26750],
            ['sku' => 'PST-0115B8', 'name' => 'PRIMA JOS 865SL', 'unit' => '1L', 'category' => 'Pestisida', 'purchase_price' => 55000, 'selling_price' => 60500, 'wholesale_price' => 58850],
            ['sku' => 'PST-C385D6', 'name' => 'PRIMA JOS 865SL', 'unit' => '20L', 'category' => 'Pestisida', 'purchase_price' => 1040000, 'selling_price' => 1144000, 'wholesale_price' => 1112800],
            ['sku' => 'PST-6515BE', 'name' => 'PRIMA JOS 865SL', 'unit' => '400ML', 'category' => 'Pestisida', 'purchase_price' => 28000, 'selling_price' => 30800, 'wholesale_price' => 29960],
            ['sku' => 'PST-A536B1', 'name' => 'PRIMA KUAT 276SL', 'unit' => '1L', 'category' => 'Pestisida', 'purchase_price' => 48000, 'selling_price' => 52800, 'wholesale_price' => 51360],
            ['sku' => 'PST-531ED7', 'name' => 'PRIMA KUAT 276SL', 'unit' => '20L', 'category' => 'Pestisida', 'purchase_price' => 45000, 'selling_price' => 49500, 'wholesale_price' => 48150],
            ['sku' => 'PST-5AE58F', 'name' => 'PRIMA KUAT 276SL', 'unit' => '5L', 'category' => 'Pestisida', 'purchase_price' => 47000, 'selling_price' => 51700, 'wholesale_price' => 50290],
            ['sku' => 'PST-C62B5C', 'name' => 'PRIMA LARIS', 'unit' => '1L', 'category' => 'Pestisida', 'purchase_price' => 210000, 'selling_price' => 231000, 'wholesale_price' => 224700],
            ['sku' => 'PST-1DB04D', 'name' => 'PRIMA LARIS', 'unit' => '250ML', 'category' => 'Pestisida', 'purchase_price' => 60000, 'selling_price' => 66000, 'wholesale_price' => 64200],
            ['sku' => 'PST-0FA53F', 'name' => 'PRIMA LARIS', 'unit' => '500ML', 'category' => 'Pestisida', 'purchase_price' => 115000, 'selling_price' => 126500, 'wholesale_price' => 123050],
            ['sku' => 'PST-46FC80', 'name' => 'PRIMA LIMA', 'unit' => '1L', 'category' => 'Pestisida', 'purchase_price' => 63000, 'selling_price' => 69300, 'wholesale_price' => 67410],
            ['sku' => 'PST-E132DD', 'name' => 'PRIMA LIMA', 'unit' => '20L', 'category' => 'Pestisida', 'purchase_price' => 62000, 'selling_price' => 68200, 'wholesale_price' => 66340],
            ['sku' => 'PST-71FE32', 'name' => 'PRIMA LIMA', 'unit' => '5L', 'category' => 'Pestisida', 'purchase_price' => 315000, 'selling_price' => 346500, 'wholesale_price' => 337050],
            ['sku' => 'PST-B0DE3D', 'name' => 'PRIMA MECT', 'unit' => '100ML', 'category' => 'Pestisida', 'purchase_price' => 155000, 'selling_price' => 170500, 'wholesale_price' => 165850],
            ['sku' => 'PST-3C2509', 'name' => 'PRIMA MECT', 'unit' => '50ML', 'category' => 'Pestisida', 'purchase_price' => 80000, 'selling_price' => 88000, 'wholesale_price' => 85600],
            ['sku' => 'PST-E89AA8', 'name' => 'PRIMA NET 40WP', 'unit' => '100GR', 'category' => 'Pestisida', 'purchase_price' => 19500, 'selling_price' => 21450, 'wholesale_price' => 20865],
            ['sku' => 'PST-ABCCA9', 'name' => 'PRIMA QUICK 36/6WP', 'unit' => '50GR', 'category' => 'Pestisida', 'purchase_price' => 28750, 'selling_price' => 31625, 'wholesale_price' => 31697],
            ['sku' => 'PST-53BF99', 'name' => 'PRIMA REJEN 55SC', 'unit' => '100ML', 'category' => 'Pestisida', 'purchase_price' => 22000, 'selling_price' => 24200, 'wholesale_price' => 23540],
            ['sku' => 'PST-97B094', 'name' => 'PRIMA REJEN 55SC', 'unit' => '200ML', 'category' => 'Pestisida', 'purchase_price' => 40500, 'selling_price' => 44550, 'wholesale_price' => 43335],
            ['sku' => 'PST-4294A7', 'name' => 'PRIMA REJEN 55SC', 'unit' => '400ML', 'category' => 'Pestisida', 'purchase_price' => 73500, 'selling_price' => 80850, 'wholesale_price' => 78645],
            ['sku' => 'PST-E9E4F6', 'name' => 'PRIMA REJEN 55SC', 'unit' => '50ML', 'category' => 'Pestisida', 'purchase_price' => 13000, 'selling_price' => 14300, 'wholesale_price' => 13910],
            ['sku' => 'PST-5EA5EB', 'name' => 'PRIMA RICE', 'unit' => '100ML', 'category' => 'Pestisida', 'purchase_price' => 0, 'selling_price' => 0, 'wholesale_price' => 0],
            ['sku' => 'PST-AAC492', 'name' => 'PRIMA TRON 135SL', 'unit' => '1L', 'category' => 'Pestisida', 'purchase_price' => 26000, 'selling_price' => 28600, 'wholesale_price' => 27820],
            ['sku' => 'PST-CEC6D2', 'name' => 'PRIMA TRON 135SL', 'unit' => '20L', 'category' => 'Pestisida', 'purchase_price' => 480000, 'selling_price' => 528000, 'wholesale_price' => 513600],
            ['sku' => 'PST-DBB336', 'name' => 'PRIMA TRON 135SL', 'unit' => '5L', 'category' => 'Pestisida', 'purchase_price' => 125000, 'selling_price' => 137500, 'wholesale_price' => 133750],
            ['sku' => 'PST-562A02', 'name' => 'PRIMA ZEB 80WP', 'unit' => '200GR', 'category' => 'Pestisida', 'purchase_price' => 24000, 'selling_price' => 26400, 'wholesale_price' => 25680],
            ['sku' => 'PST-BF4C69', 'name' => 'PRIMA ZEB 80WP', 'unit' => '800GR', 'category' => 'Pestisida', 'purchase_price' => 90000, 'selling_price' => 99000, 'wholesale_price' => 96300],
            ['sku' => 'PST-431166', 'name' => 'PRIMA ZOL 250EC', 'unit' => '250ML', 'category' => 'Pestisida', 'purchase_price' => 82000, 'selling_price' => 90200, 'wholesale_price' => 87740],
            ['sku' => 'PST-9736FB', 'name' => 'PRIMA ZOL 250EC', 'unit' => '80ML', 'category' => 'Pestisida', 'purchase_price' => 30000, 'selling_price' => 33000, 'wholesale_price' => 32100],
            ['sku' => 'PST-9FCA1C', 'name' => 'PRIMOR TMA', 'unit' => '1L', 'category' => 'Pestisida', 'purchase_price' => 17000, 'selling_price' => 18700, 'wholesale_price' => 18190],
            ['sku' => 'PST-26999F', 'name' => 'AMBI ZEUS', 'unit' => '1L', 'category' => 'Pestisida', 'purchase_price' => 125000, 'selling_price' => 137500, 'wholesale_price' => 133750],
            ['sku' => 'PST-AF14C5', 'name' => 'AMBI ZEUS', 'unit' => '500ML', 'category' => 'Pestisida', 'purchase_price' => 65000, 'selling_price' => 71500, 'wholesale_price' => 69550],
            ['sku' => 'PST-19B8B6', 'name' => 'PRIMA BEST', 'unit' => '1L', 'category' => 'Pestisida', 'purchase_price' => 44000, 'selling_price' => 48400, 'wholesale_price' => 47080],
            ['sku' => 'PST-28A030', 'name' => 'PRIMA NOID', 'unit' => '100GR*100', 'category' => 'Pestisida', 'purchase_price' => 53500, 'selling_price' => 58850, 'wholesale_price' => 57245],
            ['sku' => 'PST-A6428F', 'name' => 'REFILLXONE 135SL', 'unit' => '1L*20', 'category' => 'Pestisida', 'purchase_price' => 29500, 'selling_price' => 32450, 'wholesale_price' => 31565],
            ['sku' => 'PST-EDB9F4', 'name' => 'REFILLXONE 135SL', 'unit' => '5L*4', 'category' => 'Pestisida', 'purchase_price' => 28500, 'selling_price' => 31350, 'wholesale_price' => 30495],
            ['sku' => 'PST-6DFE55', 'name' => 'REFILLXONE 135SL', 'unit' => '20L', 'category' => 'Pestisida', 'purchase_price' => 27500, 'selling_price' => 30250, 'wholesale_price' => 29425],
            ['sku' => 'PST-87ABBE', 'name' => 'YAPEN 425EC', 'unit' => '400ML', 'category' => 'Pestisida', 'purchase_price' => 0, 'selling_price' => 0, 'wholesale_price' => 0],
            ['sku' => 'PST-009FB8', 'name' => 'PROMOTRIN PLUS 250EC', 'unit' => '400ML', 'category' => 'Pestisida', 'purchase_price' => 49600, 'selling_price' => 54560, 'wholesale_price' => 53072],
            ['sku' => 'PST-F98172', 'name' => 'PROMOTRIN PLUS 250EC', 'unit' => '1L', 'category' => 'Pestisida', 'purchase_price' => 114000, 'selling_price' => 125400, 'wholesale_price' => 121980],
        ];

        foreach ($products as $row) {
            $category = $categories[$row['category']] ?? $categories['Pestisida'];

            $product = Product::withoutGlobalScopes()->updateOrCreate(
                [
                    'branch_id' => $branchId,
                    'sku' => $row['sku'],
                ],
                [
                    'category_id' => $category->id,
                    'name' => $row['name'],
                    'unit' => $row['unit'] ?? 'PCS',
                    'purchase_price' => $row['purchase_price'],
                    'selling_price' => $row['selling_price'],
                    'stock' => 0, // CRITICAL: Mutlak 0 untuk Clean Slate
                ]
            );

            // Sync Multi-Price (Harga Eceran & Harga Grosir)
            if ($levelEceran && $row['selling_price'] > 0) {
                ProductPrice::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'price_level_id' => $levelEceran->id,
                    ],
                    [
                        'price' => $row['selling_price'],
                        'customer_group_id' => $groupRetail?->id,
                    ]
                );
            }

            if ($levelGrosir && ! empty($row['wholesale_price'])) {
                ProductPrice::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'price_level_id' => $levelGrosir->id,
                    ],
                    [
                        'price' => $row['wholesale_price'],
                        'customer_group_id' => $groupGrosir?->id,
                    ]
                );
            }

            // Sync per-branch inventory table with 0 stock
            foreach ($activeBranches as $b) {
                $wh = Warehouse::withoutGlobalScopes()
                    ->where('branch_id', $b->id)
                    ->where('is_active', true)
                    ->first();

                Inventory::withoutGlobalScopes()->updateOrCreate(
                    [
                        'branch_id' => $b->id,
                        'product_id' => $product->id,
                    ],
                    [
                        'warehouse_id' => $wh?->id,
                        'quantity' => 0, // CRITICAL: Mutlak 0
                    ]
                );
            }
        }
    }
}
