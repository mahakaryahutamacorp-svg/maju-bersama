<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerAccessGates();
    }

    /**
     * Matriks RBAC Backoffice:
     *  - master       : semua modul.
     *  - branch_admin : Dashboard & Kasir, Gudang & Inventaris, Katalog Produk, Data Pelanggan.
     *  - cashier      : Dashboard & Kasir saja.
     */
    protected function registerAccessGates(): void
    {
        // Modul Enterprise: Penjualan & Piutang (AR), Pembelian & Hutang (AP), Keuangan & Akuntansi, Pusat Laporan.
        Gate::define('access-enterprise', fn (User $user) => $user->isMaster());
        Gate::define('view-accounting', fn (User $user) => $user->isMaster());

        // Pengaturan sistem: backup database, manajemen cabang, preview data, master data penuh.
        Gate::define('manage-system', fn (User $user) => $user->isMaster());

        // Divisi Gudang & Inventaris.
        Gate::define('access-inventory', fn (User $user) => $user->isMaster() || $user->isBranchAdmin());

        // Sebagian Master Data: Katalog Produk & Data Pelanggan.
        Gate::define('manage-catalog', fn (User $user) => $user->isMaster() || $user->isBranchAdmin());

        // Operasional Cabang: Kas, Biaya (Expenses), Aset Tetap, Pembelian PO & Retur, Mutasi, Pembayaran.
        Gate::define('manage-branch-operations', fn (User $user) => $user->isMaster() || $user->isBranchAdmin());

        // Riwayat Penjualan & Piutang (AR) Cabang
        Gate::define('view-sales-history', fn (User $user) => $user->isMaster() || $user->isBranchAdmin());
        Gate::define('view-ar-reports', fn (User $user) => $user->isMaster() || $user->isBranchAdmin());

        // Pesan Internal: Pusat dan admin cabang yang terikat ke satu cabang.
        Gate::define('use-internal-messages', fn (User $user) => $user->isMaster()
            || ($user->isBranchAdmin() && $user->branch_id !== null));

        // Model Policies
        Gate::policy(\App\Models\Sale::class, \App\Policies\SalePolicy::class);
        Gate::policy(\App\Models\Expense::class, \App\Policies\ExpensePolicy::class);
        Gate::policy(\App\Models\Customer::class, \App\Policies\CustomerPolicy::class);
    }
}
