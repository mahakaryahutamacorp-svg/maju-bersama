<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BusinessStructureSeeder extends Seeder
{
    /**
     * Operational tables that count as transaction history for a branch.
     * Master data such as products or warehouses does not block cleanup.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    private const BRANCH_HISTORY = [
        ['sales', 'branch_id'],
        ['purchase_orders', 'branch_id'],
        ['goods_receipts', 'branch_id'],
        ['payments', 'branch_id'],
        ['journal_headers', 'branch_id'],
        ['stock_transfers', 'source_branch_id'],
        ['stock_transfers', 'destination_branch_id'],
        ['stock_adjustments', 'branch_id'],
        ['expenses', 'branch_id'],
        ['sales_returns', 'branch_id'],
        ['purchase_returns', 'branch_id'],
        ['supplier_payments', 'branch_id'],
        ['cash_transfers', 'branch_id'],
        ['cash_register_shifts', 'branch_id'],
        ['purchases', 'branch_id'],
    ];

    /**
     * @var array<int, array{0: string, 1: string}>
     */
    private const USER_HISTORY = [
        ['sales', 'created_by'],
        ['journal_headers', 'user_id'],
        ['cash_register_shifts', 'user_id'],
        ['stock_adjustments', 'user_id'],
        ['internal_messages', 'sender_id'],
    ];

    public function run(): void
    {
        $this->removeDisposableBranch(
            Branch::withTrashed()
                ->where('code', 'MAJUBERSAMA-5')
                ->orWhereRaw('LOWER(name) = ?', ['majubersama 5'])
                ->get(),
            'cabang uji majubersama 5',
        );

        $this->removeDisposableBranch(
            Branch::withTrashed()->where('code', 'CBG-PAKAN')->get(),
            'duplikat TOKO PAKAN (CBG-PAKAN)',
            requireNoUsers: true,
        );

        $pupukOffice = $this->ensureBranch('OFFICE-PUPUK', 'MAJU BERSAMA');
        $this->ensureBranch('GUDANG-PUPUK', 'MAJU BERSAMA', $pupukOffice->id);
        $this->ensureBranch('CBG-ABI', 'TOKO MAJU BERSAMA ABI', $pupukOffice->id);
        $this->ensureBranch('CBG-MAMA', 'TOKO MAJU BERSAMA MAMA', $pupukOffice->id);
        $this->ensureBranch('CBG-AROFAH', 'TOKO AROFAH', $pupukOffice->id);
        $this->ensureBranch('CBG-TULUSAYU', 'TOKO MUTIARA TANI', $pupukOffice->id);
        $this->ensureBranch('CBG-LUBUKHARJO', 'TOKO LUBUK HARJO', $pupukOffice->id);

        $pakan = $this->ensureBranch('PAKAN', 'CV.DEKA AGRO MANDIRI');

        $this->ensureUser('gudang@pupuk.178', 'Gudang Pupuk', 'GUDANG-PUPUK', 'branch_admin');
        $this->ensureUser('admin@mb.abi', 'Admin ABI', 'CBG-ABI', 'branch_admin');
        $this->ensureUser('pipin@kasir.pupuk', 'Pipin', 'CBG-ABI', 'cashier');
        $this->ensureUser('eva@admin.178', 'Eva', 'CBG-MAMA', 'branch_admin');
        $this->ensureUser('yayan@admin.pupuk', 'Yayan', 'CBG-AROFAH', 'branch_admin');
        $this->ensureUser('ipin@admin.178', 'Ipin', 'CBG-TULUSAYU', 'branch_admin');
        $this->ensureUser('triono@admin.pupuk', 'Triono', 'CBG-LUBUKHARJO', 'branch_admin');

        $this->ensureUser('gudang@pakan.178', 'Gudang Pakan', 'PAKAN', 'branch_admin');
        $this->ensureUser('asep@mb.pkn', 'Asep', 'PAKAN', 'branch_admin');
        $this->ensureUser('kasir@pakan.mb', 'Kasir Pakan', 'PAKAN', 'cashier');
        $this->command?->info("Pakan disatukan pada {$pakan->name} ({$pakan->code}), terpisah dari pupuk.");
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Branch>  $branches
     */
    private function removeDisposableBranch($branches, string $label, bool $requireNoUsers = false): void
    {
        foreach ($branches as $branch) {
            if ($this->isBlueprintCode($branch->code)) {
                $this->command?->warn("Lewati {$label}: kode {$branch->code} dipakai blueprint.");

                continue;
            }

            if ($requireNoUsers && $branch->users()->withTrashed()->exists()) {
                $this->command?->warn("Lewati {$label}: masih punya user, jadi bukan duplikat kosong.");

                continue;
            }

            if ($this->branchHasTransactions($branch)) {
                $this->command?->warn("Lewati {$label} ({$branch->code}): sudah memiliki riwayat transaksi.");

                continue;
            }

            $blockingUser = $branch->users()->withTrashed()->get()->first(
                fn (User $user) => $this->userHasTransactions($user),
            );

            if ($blockingUser instanceof User) {
                $this->command?->warn("Lewati {$label} ({$branch->code}): user {$blockingUser->email} sudah memiliki riwayat transaksi.");

                continue;
            }

            $branch->users()->withTrashed()->get()->each->forceDelete();
            $branch->forceDelete();
            $this->command?->info("Cabang {$branch->name} ({$branch->code}) dihapus karena tidak punya riwayat transaksi.");
        }
    }

    private function ensureBranch(string $code, string $name, ?int $parentId = null): Branch
    {
        $attributes = [
            'name' => $name,
            'parent_id' => $parentId,
            'is_active' => true,
            'timezone' => 'Asia/Jakarta',
            'tax_enabled' => true,
            'tax_rate' => '11.00',
        ];

        $branch = Branch::withTrashed()->updateOrCreate(
            ['code' => $code],
            $attributes,
        );

        if ($branch->trashed()) {
            $branch->restore();
        }

        return $branch;
    }

    private function ensureUser(string $email, string $name, string $branchCode, string $role): void
    {
        $branch = Branch::query()->where('code', $branchCode)->first();

        if ($branch === null) {
            $this->command?->warn("User {$email} tidak dibuat: cabang {$branchCode} tidak ada.");

            return;
        }

        $user = User::withTrashed()->firstOrNew(['email' => $email]);
        $user->branch_id = $branch->id;
        $user->name = $name;
        $user->role = $role;
        $user->is_active = true;

        if (! $user->exists) {
            $user->password = 'password';
        }

        $user->save();

        if ($user->trashed()) {
            $user->restore();
        }
    }

    private function branchHasTransactions(Branch $branch): bool
    {
        foreach (self::BRANCH_HISTORY as [$table, $column]) {
            if ($this->columnHasValue($table, $column, $branch->id)) {
                return true;
            }
        }

        return false;
    }

    private function userHasTransactions(User $user): bool
    {
        foreach (self::USER_HISTORY as [$table, $column]) {
            if ($this->columnHasValue($table, $column, $user->id)) {
                return true;
            }
        }

        return false;
    }

    private function columnHasValue(string $table, string $column, int $id): bool
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return false;
        }

        return DB::table($table)->where($column, $id)->exists();
    }

    private function isBlueprintCode(string $code): bool
    {
        return in_array($code, [
            'OFFICE-PUPUK',
            'GUDANG-PUPUK',
            'CBG-ABI',
            'CBG-MAMA',
            'CBG-AROFAH',
            'CBG-TULUSAYU',
            'CBG-LUBUKHARJO',
            'PAKAN',
        ], true);
    }
}
