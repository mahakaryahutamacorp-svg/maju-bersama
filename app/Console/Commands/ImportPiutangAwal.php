<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\Sale;
use App\Models\User;
use App\Services\JournalPostingService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportPiutangAwal extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:piutang-awal
                            {filepath? : Path ke file CSV (default: storage/app/import_piutang_awal.csv)}
                            {--branch= : ID atau Kode Cabang tujuan (default: Cabang Pusat/Pertama)}
                            {--journal : Otomatis buat jurnal akuntansi saldo awal (Dr. Piutang Usaha / Cr. Modal)}
                            {--prefix= : Prefix tambahan untuk nomor nota/referensi (misal: PA-)}
                            {--dry-run : Simulasi import tanpa menyimpan perubahan ke database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import riwayat saldo piutang awal pelanggan dari file CSV ke tabel sales dan penjurnalan akuntansi';

    /**
     * Execute the console command.
     */
    public function handle(JournalPostingService $journalPostingService): int
    {
        $rawPath = $this->argument('filepath') ?: 'storage/app/import_piutang_awal.csv';
        $filepath = file_exists($rawPath) ? $rawPath : base_path($rawPath);

        if (! file_exists($filepath)) {
            $this->error("File CSV tidak ditemukan di: {$filepath}");
            $this->line("Pastikan file berada di path tersebut atau tentukan path secara manual.");
            $this->line("Contoh: php artisan import:piutang-awal storage/app/import_piutang_awal.csv");

            return self::FAILURE;
        }

        $this->info("=== MEMULAI IMPORT SALDO PIUTANG AWAL ===");
        $this->line("Lokasi Berkas : {$filepath}");

        // 1. Tentukan Cabang
        $branch = $this->resolveBranch();
        if (! $branch) {
            $this->error("Tidak ada data Cabang (Branch) yang aktif di sistem.");

            return self::FAILURE;
        }
        $this->line("Cabang Tujuan : [{$branch->id}] {$branch->code} - {$branch->name}");

        // 2. Tentukan User Pembuat (Actor)
        $userId = User::where('branch_id', $branch->id)->value('id') ?? User::value('id');

        // 3. Tentukan Akun Akuntansi jika mode jurnal aktif
        $withJournal = $this->option('journal');
        $arAccount = null;
        $equityAccount = null;

        if ($withJournal) {
            $arAccount = ChartOfAccount::where('code', '1130')->first()
                ?? ChartOfAccount::where('type', 'asset')->where('name', 'like', '%piutang%')->first();

            $equityAccount = ChartOfAccount::where('code', '3110')->first()
                ?? ChartOfAccount::where('type', 'equity')->where('name', 'like', '%modal%')->first()
                ?? ChartOfAccount::where('type', 'equity')->first();

            if (! $arAccount || ! $equityAccount) {
                $this->error("Akun perkiraan (COA) untuk Piutang Usaha (1130) atau Modal (3110) tidak ditemukan.");

                return self::FAILURE;
            }

            $this->line("Akun Debit    : [{$arAccount->code}] {$arAccount->name}");
            $this->line("Akun Kredit   : [{$equityAccount->code}] {$equityAccount->name}");
        }

        $isDryRun = (bool) $this->option('dry-run');
        if ($isDryRun) {
            $this->warn("MODE DRY-RUN AKTIF: Simulasi dijalankan tanpa menyimpan perubahan ke database.");
        }

        // 4. Buka dan Analisis File CSV
        $handle = fopen($filepath, 'r');
        if (! $handle) {
            $this->error("Gagal membuka file CSV.");

            return self::FAILURE;
        }

        // Deteksi delimiter (koma atau titik koma)
        $firstLine = fgets($handle);
        $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
        rewind($handle);

        // Baca baris header
        $header = fgetcsv($handle, 4096, $delimiter);
        if (! $header) {
            $this->error("Header CSV kosong.");
            fclose($handle);

            return self::FAILURE;
        }

        $columnMap = $this->resolveColumnMap($header);
        if ($columnMap['name'] === -1 || $columnMap['total'] === -1) {
            $this->error("Struktur kolom CSV tidak dikenali.");
            $this->line("Header yang terbaca: " . implode(', ', array_filter($header)));
            $this->line("Dibutuhkan setidaknya kolom [NAMA / Nama Customer] dan [TOTAL / SISA].");
            fclose($handle);

            return self::FAILURE;
        }

        $defaultCustomerGroup = CustomerGroup::firstOrCreate(
            ['name' => 'Umum/Retail'],
            ['notes' => 'Pelanggan retail umum dengan harga standar.']
        );

        $customPrefix = trim((string) $this->option('prefix'));

        // Statistik
        $stats = [
            'total_rows' => 0,
            'customers_created' => 0,
            'customers_existing' => 0,
            'sales_created' => 0,
            'sales_skipped' => 0,
            'journals_created' => 0,
            'total_piutang' => 0.0,
            'errors' => [],
        ];

        DB::beginTransaction();

        try {
            $rowNumber = 1;
            while (($row = fgetcsv($handle, 4096, $delimiter)) !== false) {
                $rowNumber++;

                // Abaikan baris kosong atau baris judul/subtotal
                if (! array_filter($row)) {
                    continue;
                }

                $rawCustomerName = isset($row[$columnMap['name']]) ? trim($row[$columnMap['name']]) : '';
                if ($rawCustomerName === '' || stripos($rawCustomerName, 'TOTAL') === 0 || stripos($rawCustomerName, 'DAFTAR PIUTANG') !== false) {
                    continue;
                }

                $stats['total_rows']++;

                $rawAddress = ($columnMap['address'] !== -1 && isset($row[$columnMap['address']])) ? trim($row[$columnMap['address']]) : null;
                $rawDate = ($columnMap['date'] !== -1 && isset($row[$columnMap['date']])) ? trim($row[$columnMap['date']]) : null;
                $rawInvoice = ($columnMap['invoice'] !== -1 && isset($row[$columnMap['invoice']])) ? trim($row[$columnMap['invoice']]) : '';
                $rawTotal = isset($row[$columnMap['total']]) ? $row[$columnMap['total']] : '0';

                $nominal = $this->parseAmount($rawTotal);
                if ($nominal <= 0) {
                    $stats['sales_skipped']++;
                    continue;
                }

                // A. Master Data Pelanggan
                $customer = Customer::withoutGlobalScopes()
                    ->where('branch_id', $branch->id)
                    ->whereRaw('LOWER(name) = ?', [strtolower($rawCustomerName)])
                    ->first();

                if (! $customer) {
                    $customer = Customer::create([
                        'branch_id' => $branch->id,
                        'name' => $rawCustomerName,
                        'address' => $rawAddress ?: null,
                        'customer_group_id' => $defaultCustomerGroup->id,
                    ]);
                    $stats['customers_created']++;
                } else {
                    $stats['customers_existing']++;
                    if (empty($customer->address) && ! empty($rawAddress)) {
                        $customer->address = $rawAddress;
                        $customer->save();
                    }
                }

                // B. Tanggal Nota
                $transactionDate = $this->parseDate($rawDate);

                // C. Nomor Referensi / Nota (Unik)
                $finalReceiptNumber = $this->generateUniqueReceiptNumber(
                    $rawInvoice,
                    $rawCustomerName,
                    $transactionDate,
                    $customPrefix,
                    $branch->id
                );

                // D. Simpan Riwayat Tagihan ke Tabel Sales
                $sale = new Sale();
                $sale->branch_id = $branch->id;
                $sale->customer_id = $customer->id;
                $sale->receipt_number = $finalReceiptNumber;
                $sale->total_amount = (int) round($nominal);
                $sale->discount_amount = 0;
                $sale->paid_amount = 0;
                $sale->payment_status = 'UNPAID';
                $sale->payment_method = 'kredit';
                $sale->status = 'completed';
                $sale->due_date = $transactionDate->toDateString();
                $sale->created_by = $userId;
                $sale->created_at = $transactionDate;
                $sale->updated_at = $transactionDate;
                $sale->save();

                $stats['sales_created']++;
                $stats['total_piutang'] += $nominal;

                // E. Penjurnalan Otomatis (Opsional)
                if ($withJournal && $arAccount && $equityAccount) {
                    $journalPostingService->post([
                        'branch_id' => $branch->id,
                        'user_id' => $userId,
                        'transaction_date' => $transactionDate->toDateString(),
                        'reference_number' => "JV-{$finalReceiptNumber}",
                        'description' => "Saldo Awal Piutang - {$customer->name} (Nota: {$finalReceiptNumber})",
                        'lines' => [
                            [
                                'chart_of_account_id' => $arAccount->id,
                                'debit' => $nominal,
                                'credit' => 0,
                                'memo' => "Saldo Awal Piutang {$customer->name}",
                            ],
                            [
                                'chart_of_account_id' => $equityAccount->id,
                                'debit' => 0,
                                'credit' => $nominal,
                                'memo' => "Saldo Awal Ekuitas/Modal",
                            ],
                        ],
                    ]);
                    $stats['journals_created']++;
                }
            }

            fclose($handle);

            if ($isDryRun) {
                DB::rollBack();
                $this->warn("Simulasi selesai. Semua perubahan di-rollback (tidak ada data yang tersimpan).");
            } else {
                DB::commit();
                $this->info("Import data piutang awal berhasil disimpan ke database!");
            }

            // Tampilkan Ringkasan
            $this->newLine();
            $this->table(
                ['Parameter', 'Hasil'],
                [
                    ['Total Baris Diproses', number_format($stats['total_rows'], 0, ',', '.')],
                    ['Pelanggan Baru Dibuat', number_format($stats['customers_created'], 0, ',', '.')],
                    ['Pelanggan Lama Ditemukan', number_format($stats['customers_existing'], 0, ',', '.')],
                    ['Faktur Piutang Dibuat (Sales)', number_format($stats['sales_created'], 0, ',', '.')],
                    ['Faktur Dilewati (Nominal 0)', number_format($stats['sales_skipped'], 0, ',', '.')],
                    ['Total Nilai Piutang Awal', 'Rp ' . number_format($stats['total_piutang'], 0, ',', '.')],
                    ['Jurnal Akuntansi Dibukukan', $withJournal ? number_format($stats['journals_created'], 0, ',', '.') : 'Tidak Diaktifkan'],
                ]
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            if (is_resource($handle)) {
                fclose($handle);
            }
            $this->error("Terjadi kesalahan saat proses import: " . $e->getMessage());
            $this->line("Trace: " . $e->getFile() . " baris " . $e->getLine());

            return self::FAILURE;
        }
    }

    /**
     * Resolusi branch tujuan.
     */
    protected function resolveBranch(): ?Branch
    {
        $branchOpt = $this->option('branch');

        if ($branchOpt) {
            return Branch::where('id', $branchOpt)
                ->orWhere('code', $branchOpt)
                ->orWhere('name', $branchOpt)
                ->first();
        }

        return Branch::where('code', 'PUSAT')
            ->orWhere('code', 'MBP')
            ->orWhere('name', 'like', '%pusat%')
            ->first() ?? Branch::where('is_active', true)->first() ?? Branch::first();
    }

    /**
     * Petakan posisi kolom CSV secara fleksibel.
     */
    protected function resolveColumnMap(array $header): array
    {
        $map = [
            'name' => -1,
            'address' => -1,
            'date' => -1,
            'invoice' => -1,
            'total' => -1,
        ];

        foreach ($header as $idx => $rawCol) {
            $col = strtolower(trim($rawCol));

            // Kolom Nama Pelanggan
            if ($map['name'] === -1 && in_array($col, ['nama', 'nama customer', 'customer', 'pelanggan'])) {
                $map['name'] = $idx;
            }
            // Kolom Alamat
            elseif ($map['address'] === -1 && in_array($col, ['alamat', 'address', 'wilayah'])) {
                $map['address'] = $idx;
            }
            // Kolom Tanggal Nota
            elseif ($map['date'] === -1 && in_array($col, ['tgl nota', 'tanggal nota', 'tgl', 'tanggal', 'date'])) {
                $map['date'] = $idx;
            }
            // Kolom Nomor Nota / Invoice
            elseif ($map['invoice'] === -1 && in_array($col, ['no nota', 'invo', 'no nota / invo', 'no invoice', 'invoice', 'receipt_number'])) {
                $map['invoice'] = $idx;
            }
            // Kolom Total / Sisa Tagihan
            elseif (in_array($col, ['total', 'sisa', 'sub total', 'nominal', 'saldo', 'piutang', 'hutang'])) {
                if ($map['total'] === -1 || in_array($col, ['total', 'sisa'])) {
                    $map['total'] = $idx;
                }
            }
        }

        return $map;
    }

    /**
     * Parse nominal uang ke float murni.
     */
    protected function parseAmount(string|float|int|null $raw): float
    {
        if ($raw === null) {
            return 0.0;
        }

        $str = trim((string) $raw);
        if ($str === '' || $str === '-' || $str === '0') {
            return 0.0;
        }

        $str = str_ireplace(['rp', ' ', '"', "'"], '', $str);

        // Jika memiliki titik dan koma, tentukan pemisah desimal
        if (str_contains($str, '.') && str_contains($str, ',')) {
            if (strrpos($str, ',') > strrpos($str, '.')) {
                // Contoh format Indonesia: 11.276.000,50
                $str = str_replace('.', '', $str);
                $str = str_replace(',', '.', $str);
            } else {
                // Format internasional: 11,276,000.50
                $str = str_replace(',', '', $str);
            }
        } elseif (str_contains($str, ',')) {
            $parts = explode(',', $str);
            if (count($parts) > 1 && strlen(end($parts)) === 3) {
                // Pemisah ribuan dengan koma: 11,276,000
                $str = str_replace(',', '', $str);
            } else {
                $str = str_replace(',', '.', $str);
            }
        } elseif (str_contains($str, '.')) {
            $parts = explode('.', $str);
            if (count($parts) > 1 && strlen(end($parts)) === 3) {
                // Pemisah ribuan dengan titik: 11.276.000
                $str = str_replace('.', '', $str);
            }
        }

        return (float) preg_replace('/[^\d.]/', '', $str);
    }

    /**
     * Parse string tanggal dari berbagai format menjadi Carbon.
     */
    protected function parseDate(?string $rawDate): Carbon
    {
        if (empty($rawDate)) {
            return now();
        }

        $rawDate = trim($rawDate);
        if (str_contains($rawDate, ' ')) {
            $rawDate = explode(' ', $rawDate)[0];
        }

        // Format YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $rawDate)) {
            return Carbon::parse($rawDate)->startOfDay();
        }

        // Format dengan slash (M/D/Y atau D/M/Y)
        if (str_contains($rawDate, '/')) {
            $parts = explode('/', $rawDate);
            if (count($parts) === 3) {
                $p1 = (int) $parts[0];
                $p2 = (int) $parts[1];
                $y = (int) $parts[2];

                // Koreksi tahun jika typo (misal 3036 -> 2026)
                if ($y > 2090) {
                    $y = 2026;
                }

                if ($p1 > 12) {
                    // Pasti D/M/Y (misal 17/12/2017)
                    return Carbon::createFromDate($y, $p2, $p1)->startOfDay();
                } else {
                    // M/D/Y (misal 9/21/2023)
                    return Carbon::createFromDate($y, $p1, $p2)->startOfDay();
                }
            }
        }

        try {
            return Carbon::parse($rawDate)->startOfDay();
        } catch (\Throwable $e) {
            return now();
        }
    }

    /**
     * Generate Receipt Number yang unik dan tidak bentrok di tabel sales.
     */
    protected function generateUniqueReceiptNumber(
        string $rawInvoice,
        string $customerName,
        Carbon $date,
        string $customPrefix,
        int $branchId
    ): string {
        $cleanInvoice = trim($rawInvoice);

        // Jika nomor nota kosong atau '0', berikan format penanda khusus
        if ($cleanInvoice === '' || $cleanInvoice === '0') {
            $slugName = Str::slug($customerName);
            $cleanInvoice = "NOTA-{$date->format('Ymd')}-{$slugName}";
        }

        $candidate = $customPrefix ? "{$customPrefix}{$cleanInvoice}" : $cleanInvoice;

        // Cek keunikan di tabel sales
        $exists = Sale::withoutGlobalScopes()->where('receipt_number', $candidate)->exists();
        if (! $exists) {
            return $candidate;
        }

        // Jika sudah ada, beri akhiran penomoran unik
        $counter = 1;
        while (Sale::withoutGlobalScopes()->where('receipt_number', "{$candidate}-{$counter}")->exists()) {
            $counter++;
        }

        return "{$candidate}-{$counter}";
    }
}
