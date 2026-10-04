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
    }
}
