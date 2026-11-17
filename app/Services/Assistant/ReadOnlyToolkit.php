<?php

namespace App\Services\Assistant;

use App\Models\Customer;
use App\Models\GoodsReceipt;
use App\Models\Inventory;
use App\Models\JournalHeader;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Services\FinanceDashboardService;
use Illuminate\Support\Facades\Gate;

class ReadOnlyToolkit
{
    public function __construct(
        private readonly SystemGuide $guide,
        private readonly UsageAdvice $advice,
        private readonly FinanceDashboardService $finance,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{found: bool, summary: string, links: array<int, array{label: string, url: string}>}
     */
    public function run(string $tool, array $arguments, User $user): array
    {
        if (! AssistantContract::allows($tool)) {
            throw new AssistantToolDenied($tool);
        }

        return match ($tool) {
            'search_guide' => $this->guide->search((string) ($arguments['query'] ?? '')),
            'usage_advice' => $this->advice->advise((string) ($arguments['query'] ?? '')),
            'smart_search' => $this->smartSearch($user, (string) ($arguments['query'] ?? '')),
            'stock_on_hand' => $this->stockOnHand($user, (string) ($arguments['query'] ?? '')),
            'list_receivables' => $this->listReceivables((string) ($arguments['query'] ?? '')),
            'sales_summary' => $this->salesSummary((string) ($arguments['period'] ?? 'today')),
            'recent_transactions' => $this->recentTransactions($user, (string) ($arguments['query'] ?? '')),
            'cash_position' => $this->cashPosition($user),
            'trace_document' => $this->traceDocument($user, (string) ($arguments['query'] ?? '')),
            default => throw new AssistantToolDenied($tool),
        };
    }

    /**
     * Pencarian cerdas lintas pelanggan, pemasok, produk, dan dokumen.
     *
     * @return array{found: bool, summary: string, links: array<int, array{label: string, url: string}>}
     */
    private function smartSearch(User $user, string $query): array
    {
        $term = trim($query);
        if (mb_strlen($term) < 2) {
            return [
                'found' => false,
                'summary' => 'Sebutkan nama pelanggan, pemasok, produk, SKU, atau nomor dokumen untuk dicari.',
                'links' => [
                    ['label' => 'Pusat Laporan', 'url' => route('reports.index')],
                ],
            ];
        }

        $sections = [];
        $links = [];

        $this->appendCustomerMatches($term, $user, $sections, $links);
        $this->appendSupplierMatches($term, $user, $sections, $links);
        $this->appendProductMatches($user, $term, $sections, $links);
        $this->appendDocumentMatches($user, $term, $sections, $links);

        if ($sections === []) {
            return [
                'found' => false,
                'summary' => "Tidak ditemukan data yang cocok untuk \"{$term}\" di cabang yang boleh Anda lihat.",
                'links' => [
                    ['label' => 'Pusat Laporan', 'url' => route('reports.index')],
                    ['label' => 'Riwayat Penjualan', 'url' => route('reports.sales')],
                ],
            ];
        }

        return [
            'found' => true,
            'summary' => "Hasil pencarian \"{$term}\":\n\n".implode("\n\n", $sections),
            'links' => array_values($links),
        ];
    }

    /**
     * @param  array<int, string>  $sections
     * @param  array<string, array{label: string, url: string}>  $links
     */
    private function appendCustomerMatches(string $term, User $user, array &$sections, array &$links): void
    {
        $customers = Customer::query()
            ->with('branch')
            ->where(function ($builder) use ($term) {
                $builder->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->limit(5)
            ->get();

        foreach ($customers as $customer) {
            $sales = Sale::query()
                ->where('customer_id', $customer->id)
                ->latest('id')
                ->limit(5)
                ->get();

            $open = Sale::query()
                ->where('customer_id', $customer->id)
                ->where('payment_status', '!=', 'PAID')
                ->get();

            $openTotal = $open->sum(fn (Sale $sale) => max(0, (float) $sale->total_amount - (float) $sale->paid_amount));
            $paidTotal = (float) Payment::query()->where('customer_id', $customer->id)->where('type', 'AR')->sum('amount');

            $lines = [
                'Pelanggan: '.$customer->name.($customer->phone ? " ({$customer->phone})" : ''),
            ];
            if ($user->isMaster() && $customer->branch) {
                $lines[] = 'Cabang: '.$customer->branch->name;
            }
            $lines[] = 'Piutang terbuka: '.$open->count().' faktur, sisa '.$this->rupiah((float) $openTotal).'.';
            $lines[] = 'Total penerimaan piutang tercatat: '.$this->rupiah($paidTotal).'.';

            if ($sales->isEmpty()) {
                $lines[] = 'Belum ada transaksi penjualan.';
            } else {
                $lines[] = 'Transaksi terbaru:';
                foreach ($sales as $sale) {
                    $remaining = max(0, (float) $sale->total_amount - (float) $sale->paid_amount);
                    $when = $sale->created_at?->timezone(config('app.timezone'))->format('d/m/Y') ?: '-';
                    $lines[] = '- '.$when.' '.$sale->receipt_number.' '.$this->rupiah((float) $sale->total_amount)
                        .' status '.$sale->payment_status
                        .($remaining > 0 ? ', sisa '.$this->rupiah($remaining) : '');
                }
            }

            $sections[] = implode("\n", $lines);
            $links[route('backoffice.customers.show', $customer)] = [
                'label' => 'Pelanggan '.$customer->name,
                'url' => route('backoffice.customers.show', $customer),
            ];
            $links[route('reports.ar-aging')] = [
                'label' => 'Piutang Pelanggan',
                'url' => route('reports.ar-aging'),
            ];
            $links[route('backoffice.payments.receivables.create')] = [
                'label' => 'Terima Bayaran Piutang',
                'url' => route('backoffice.payments.receivables.create'),
            ];
        }
    }

    /**
     * @param  array<int, string>  $sections
     * @param  array<string, array{label: string, url: string}>  $links
     */
    private function appendSupplierMatches(string $term, User $user, array &$sections, array &$links): void
    {
        $suppliers = Supplier::query()
            ->with('branch')
            ->where(function ($builder) use ($term) {
                $builder->where('name', 'like', "%{$term}%")
                    ->orWhere('contact_person', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->limit(5)
            ->get();

        foreach ($suppliers as $supplier) {
            $purchases = Purchase::query()
                ->where('distributor_id', $supplier->id)
                ->latest('id')
                ->limit(5)
                ->get();

            $openPurchases = Purchase::query()
                ->where('distributor_id', $supplier->id)
                ->whereRaw('LOWER(status) != ?', ['paid'])
                ->get();
            $openDebt = $openPurchases->sum(fn (Purchase $purchase) => (float) $purchase->remaining_debt);

            $orders = PurchaseOrder::query()
                ->where('supplier_id', $supplier->id)
                ->latest('id')
                ->limit(3)
                ->get();

            $receiptsQuery = GoodsReceipt::query()
                ->where(function ($builder) use ($supplier) {
                    $builder->where('supplier_id', $supplier->id)
                        ->orWhere('supplier_name', 'like', '%'.$supplier->name.'%');
                })
                ->latest('id')
                ->limit(3);
            if (! $user->isMaster()) {
                $receiptsQuery->where('branch_id', $user->branch_id);
            }
            $receipts = $receiptsQuery->get();

            $lines = [
                'Pemasok: '.$supplier->name.($supplier->contact_person ? " (kontak {$supplier->contact_person})" : ''),
            ];
            if ($user->isMaster() && $supplier->branch) {
                $lines[] = 'Cabang: '.$supplier->branch->name;
            }
            $lines[] = 'Hutang terbuka: '.$openPurchases->count().' dokumen, sisa '.$this->rupiah((float) $openDebt).'.';

            if ($purchases->isNotEmpty()) {
                $lines[] = 'Pembelian/hutang terbaru:';
                foreach ($purchases as $purchase) {
                    $lines[] = '- '.($purchase->invoice_number ?: '#'.$purchase->id)
                        .' total '.$this->rupiah((float) $purchase->total_amount)
                        .', sisa '.$this->rupiah((float) $purchase->remaining_debt)
                        .', status '.$purchase->status.'.';
                }
            }

            if ($orders->isNotEmpty()) {
                $lines[] = 'Pesanan (PO) terbaru:';
                foreach ($orders as $order) {
                    $lines[] = '- '.$order->reference_number.' status '.$order->status.'.';
                }
            }

            if ($receipts->isNotEmpty()) {
                $lines[] = 'Penerimaan barang terbaru:';
                foreach ($receipts as $receipt) {
                    $when = $receipt->date?->format('d/m/Y') ?: '-';
                    $lines[] = '- '.$when.' '.$receipt->reference_number.' '.$this->rupiah((float) $receipt->total_amount).'.';
                }
            }

            if ($purchases->isEmpty() && $orders->isEmpty() && $receipts->isEmpty()) {
                $lines[] = 'Belum ada transaksi belanja tercatat untuk pemasok ini.';
            }

            $sections[] = implode("\n", $lines);
            $links[route('backoffice.suppliers.show', $supplier)] = [
                'label' => 'Pemasok '.$supplier->name,
                'url' => route('backoffice.suppliers.show', $supplier),
            ];
            $links[route('backoffice.suppliers.index')] = [
                'label' => 'Buku Pemasok',
                'url' => route('backoffice.suppliers.index'),
            ];
            if (Gate::forUser($user)->allows('view-accounting')) {
                $links[route('reports.ap-aging')] = [
                    'label' => 'Aging Hutang',
                    'url' => route('reports.ap-aging'),
                ];
            }
        }
    }

    /**
     * @param  array<int, string>  $sections
     * @param  array<string, array{label: string, url: string}>  $links
     */
    private function appendProductMatches(User $user, string $term, array &$sections, array &$links): void
    {
        $canInventory = Gate::forUser($user)->allows('access-inventory');
        $canCatalog = Gate::forUser($user)->allows('view-sellable-catalog')
            || Gate::forUser($user)->allows('manage-catalog')
            || $canInventory;

        if (! $canCatalog) {
            return;
        }

        $products = Product::query()
            ->with(['category', 'branch'])
            ->where(function ($builder) use ($term) {
                $builder->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->limit(5)
            ->get();

        foreach ($products as $product) {
            $lines = [
                'Produk: '.$product->name.' (SKU '.($product->sku ?: '-').')',
            ];
            if ($product->category) {
                $lines[] = 'Kategori: '.$product->category->name;
            }
            if ($user->isMaster() && $product->branch) {
                $lines[] = 'Cabang master produk: '.$product->branch->name;
            }
            $lines[] = 'Harga jual '.$this->rupiah((float) $product->selling_price)
                .', harga beli '.$this->rupiah((float) $product->purchase_price).'.';

            if ($canInventory) {
                $qty = (float) Inventory::query()
                    ->where('product_id', $product->id)
                    ->sum('quantity');
                $lines[] = 'Stok tersedia: '.$this->formatQty($qty).' '.($product->unit ?: 'unit').'.';
            }

            $saleHits = Sale::query()
                ->whereHas('items', fn ($items) => $items->where('product_id', $product->id))
                ->latest('id')
                ->limit(3)
                ->get();
            if ($saleHits->isNotEmpty()) {
                $lines[] = 'Penjualan yang memuat produk ini:';
                foreach ($saleHits as $sale) {
                    $when = $sale->created_at?->timezone(config('app.timezone'))->format('d/m/Y') ?: '-';
                    $lines[] = '- '.$when.' '.$sale->receipt_number.' '.$this->rupiah((float) $sale->total_amount).'.';
                }
            }

            $sections[] = implode("\n", $lines);
            $links[route('backoffice.products.index')] = [
                'label' => 'Katalog Produk',
                'url' => route('backoffice.products.index'),
            ];
            if ($canInventory) {
                $links[url('/inventory')] = [
                    'label' => 'Stok Barang',
                    'url' => url('/inventory'),
                ];
            }
        }
    }

    /**
     * @param  array<int, string>  $sections
     * @param  array<string, array{label: string, url: string}>  $links
     */
    private function appendDocumentMatches(User $user, string $term, array &$sections, array &$links): void
    {
        $sale = Sale::query()
            ->with('customer')
            ->where('receipt_number', 'like', "%{$term}%")
            ->latest('id')
            ->first();

        if ($sale !== null) {
            $remaining = max(0, (float) $sale->total_amount - (float) $sale->paid_amount);
            $when = $sale->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?: '-';
            $sections[] = implode("\n", [
                'Dokumen penjualan: '.$sale->receipt_number,
                'Pelanggan: '.($sale->customer?->name ?: 'Pelanggan Umum'),
                'Tanggal '.$when.', total '.$this->rupiah((float) $sale->total_amount)
                    .', dibayar '.$this->rupiah((float) $sale->paid_amount)
                    .', sisa '.$this->rupiah($remaining)
                    .', status '.$sale->payment_status.'.',
            ]);
            $links[route('transactions.details', ['reference' => $sale->receipt_number])] = [
                'label' => 'Detail '.$sale->receipt_number,
                'url' => route('transactions.details', ['reference' => $sale->receipt_number]),
            ];
        }

        $receiptQuery = GoodsReceipt::query()
            ->where(function ($builder) use ($term) {
                $builder->where('reference_number', 'like', "%{$term}%")
                    ->orWhere('supplier_name', 'like', "%{$term}%");
            })
            ->latest('id')
            ->limit(3);
        if (! $user->isMaster()) {
            $receiptQuery->where('branch_id', $user->branch_id);
        }
        $receipts = $receiptQuery->get();

        foreach ($receipts as $receipt) {
            $sections[] = implode("\n", [
                'Dokumen terima barang: '.$receipt->reference_number,
                'Pemasok: '.($receipt->supplier_name ?: '-'),
                'Tanggal '.($receipt->date?->format('d/m/Y') ?: '-')
                    .', total '.$this->rupiah((float) $receipt->total_amount).'.',
            ]);
            $links[route('purchases.goods-receipts.show', $receipt->id)] = [
                'label' => 'Terima '.$receipt->reference_number,
                'url' => route('purchases.goods-receipts.show', $receipt->id),
            ];
        }
    }

    private function formatQty(float $qty): string
    {
        if (abs($qty - round($qty)) < 0.0001) {
            return (string) (int) round($qty);
        }

        return rtrim(rtrim(number_format($qty, 3, ',', '.'), '0'), ',');
    }

    /**
     * @return array{found: bool, summary: string, links: array<int, array{label: string, url: string}>}
     */
    private function stockOnHand(User $user, string $query): array
    {
        if (! Gate::forUser($user)->allows('access-inventory')) {
            return $this->hidden('Stok hanya tampil bagi pengguna yang berhak membuka gudang.');
        }

        $term = trim($query);
        $rows = Inventory::query()
            ->with('product')
            ->where('quantity', '>', 0)
            ->when($term !== '', function ($builder) use ($term) {
                $builder->whereHas('product', function ($product) use ($term) {
                    $product->where('name', 'like', "%{$term}%")
                        ->orWhere('sku', 'like', "%{$term}%");
                });
            })
            ->orderByDesc('quantity')
            ->limit(8)
            ->get();

        if ($rows->isEmpty()) {
            return [
                'found' => false,
                'summary' => 'Tidak ditemukan stok yang cocok di cabang yang boleh Anda lihat.',
                'links' => [['label' => 'Stok Barang', 'url' => url('/inventory')]],
            ];
        }

        $lines = $rows->map(function (Inventory $row) {
            $name = $row->product?->name ?: 'Barang';
            $sku = $row->product?->sku ?: '-';

            return "{$name} ({$sku}): {$row->quantity} unit";
        })->implode("\n");

        return [
            'found' => true,
            'summary' => "Stok yang tersedia:\n{$lines}",
            'links' => [
                ['label' => 'Stok Barang', 'url' => url('/inventory')],
                ['label' => 'Transfer Stok', 'url' => route('stock-transfer')],
            ],
        ];
    }

    /**
     * @return array{found: bool, summary: string, links: array<int, array{label: string, url: string}>}
     */
    private function listReceivables(string $query): array
    {
        $term = trim($query);
        $sales = Sale::query()
            ->with('customer')
            ->where('payment_status', '!=', 'PAID')
            ->when($term !== '', function ($builder) use ($term) {
                $builder->where(function ($inner) use ($term) {
                    $inner->where('receipt_number', 'like', "%{$term}%")
                        ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$term}%"));
                });
            })
            ->latest('id')
            ->limit(8)
            ->get();

        if ($sales->isEmpty()) {
            return [
                'found' => false,
                'summary' => 'Tidak ditemukan piutang yang cocok di cabang yang boleh Anda lihat.',
                'links' => [['label' => 'Piutang Pelanggan', 'url' => route('reports.ar-aging')]],
            ];
        }

        $lines = $sales->map(function (Sale $sale) {
            $remaining = max(0, (float) $sale->total_amount - (float) $sale->paid_amount);
            $name = $sale->customer?->name ?: 'Pelanggan Umum';
            $date = $sale->created_at?->timezone(config('app.timezone'))->format('d/m/Y') ?: '-';

            return "{$name}, faktur {$sale->receipt_number}, {$date}, sisa {$this->rupiah($remaining)}, status {$sale->payment_status}.";
        })->implode("\n");

        return [
            'found' => true,
            'summary' => "Piutang yang belum lunas:\n{$lines}",
            'links' => [
                ['label' => 'Terima Bayaran Piutang', 'url' => route('backoffice.payments.receivables.create')],
                ['label' => 'Piutang Pelanggan', 'url' => route('reports.ar-aging')],
            ],
        ];
    }

    /**
     * @return array{found: bool, summary: string, links: array<int, array{label: string, url: string}>}
     */
    private function salesSummary(string $period): array
    {
        $query = Sale::query()->where('status', 'completed');
        $label = 'hari ini';

        if ($period === 'month') {
            $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
            $label = 'bulan ini';
        } else {
            $query->whereDate('created_at', now()->toDateString());
        }

        $count = (clone $query)->count();
        $total = (float) (clone $query)->sum('total_amount');

        return [
            'found' => $count > 0,
            'summary' => $count > 0
                ? "Penjualan {$label}: {$count} transaksi, total {$this->rupiah($total)}."
                : "Tidak ada penjualan {$label} di cabang yang boleh Anda lihat.",
            'links' => [['label' => 'Riwayat Penjualan', 'url' => route('reports.sales')]],
        ];
    }

    /**
     * @return array{found: bool, summary: string, links: array<int, array{label: string, url: string}>}
     */
    private function recentTransactions(User $user, string $query): array
    {
        $term = trim($query);
        $sales = Sale::query()
            ->with(['customer', 'branch'])
            ->when($term !== '', function ($builder) use ($term) {
                $builder->where(function ($inner) use ($term) {
                    $inner->where('receipt_number', 'like', "%{$term}%")
                        ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$term}%"));
                });
            })
            ->latest('id')
            ->limit(8)
            ->get();

        if ($sales->isEmpty()) {
            return [
                'found' => false,
                'summary' => 'Tidak ditemukan transaksi yang cocok di data yang boleh Anda lihat.',
                'links' => [['label' => 'Riwayat Penjualan', 'url' => route('reports.sales')]],
            ];
        }

        $lines = $sales->map(function (Sale $sale) use ($user) {
            $date = $sale->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?: '-';
            $name = $sale->customer?->name ?: 'Pelanggan Umum';
            $branch = $user->isMaster() ? (($sale->branch?->name ?: 'Cabang').', ') : '';

            return "{$date}, {$branch}faktur {$sale->receipt_number}, {$name}, {$this->rupiah((float) $sale->total_amount)}, status {$sale->payment_status}.";
        })->implode("\n");

        $scope = $user->isMaster()
            ? 'Transaksi terbaru di seluruh cabang:'
            : 'Transaksi terbaru di cabang Anda:';

        return [
            'found' => true,
            'summary' => "{$scope}\n{$lines}",
            'links' => [['label' => 'Riwayat Penjualan', 'url' => route('reports.sales')]],
        ];
    }

    /**
     * @return array{found: bool, summary: string, links: array<int, array{label: string, url: string}>}
     */
    private function cashPosition(User $user): array
    {
        $today = now()->toDateString();
        $snapshot = $this->finance->build($user, null, $today, $today);
        $accounts = collect($snapshot['cash_accounts']);

        if ($accounts->isEmpty()) {
            return [
                'found' => false,
                'summary' => 'Akun kas dan bank belum tersedia.',
                'links' => [['label' => 'Kas & Bank', 'url' => route('backoffice.finance.dashboard', ['tab' => 'kas_bank'])]],
            ];
        }

        $lines = $accounts->map(fn (array $account) => "[{$account['code']}] {$account['name']}: {$this->rupiah((float) $account['balance'])}")
            ->implode("\n");

        return [
            'found' => true,
            'summary' => "Posisi kas dan bank:\n{$lines}\nTotal {$this->rupiah((float) $snapshot['cash_total'])}.",
            'links' => [['label' => 'Kas & Bank', 'url' => route('backoffice.finance.dashboard', ['tab' => 'kas_bank'])]],
        ];
    }

    /**
     * @return array{found: bool, summary: string, links: array<int, array{label: string, url: string}>}
     */
    private function traceDocument(User $user, string $query): array
    {
        $term = trim($query);
        if ($term === '') {
            return $this->hidden('Sebutkan nomor faktur atau nama debitur yang ingin ditelusuri.');
        }

        $sale = Sale::query()
            ->with(['customer', 'items.product'])
            ->where(function ($builder) use ($term) {
                $builder->where('receipt_number', 'like', "%{$term}%")
                    ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', "%{$term}%"));
            })
            ->latest('id')
            ->first();

        if ($sale === null) {
            return [
                'found' => false,
                'summary' => 'Tidak ditemukan faktur atau debitur tersebut di cabang yang boleh Anda lihat.',
                'links' => [['label' => 'Riwayat Penjualan', 'url' => route('reports.sales')]],
            ];
        }

        $allocations = PaymentAllocation::query()
            ->with('payment.account')
            ->where('sale_id', $sale->id)
            ->get();

        $items = $sale->items
            ->map(fn ($item) => ($item->product?->name ?: 'Barang').' x'.$item->quantity)
            ->implode(', ');

        $remaining = max(0, (float) $sale->total_amount - (float) $sale->paid_amount);
        $lines = [
            'Faktur '.$sale->receipt_number.' atas '.($sale->customer?->name ?: 'Pelanggan Umum').'.',
            'Tanggal '.($sale->created_at?->timezone(config('app.timezone'))->format('d/m/Y') ?: '-').', total '.$this->rupiah((float) $sale->total_amount).', sudah dibayar '.$this->rupiah((float) $sale->paid_amount).', sisa '.$this->rupiah($remaining).'.',
            'Keterangan: '.($items !== '' ? $items : 'Piutang penjualan').'.',
            'Status pembayaran: '.$sale->payment_status.'.',
        ];

        if ($allocations->isEmpty()) {
            $lines[] = 'Belum ada alokasi pembayaran untuk faktur ini.';
        } else {
            $lines[] = 'Pembayaran:';
            foreach ($allocations as $allocation) {
                $payment = $allocation->payment;
                $when = $payment?->payment_date?->format('d/m/Y') ?: '-';
                $account = $payment?->account?->name ?: 'akun kas/bank';
                $lines[] = '- '.$when.' '.$this->rupiah((float) $allocation->allocated_amount).' ke '.$account.', ref '.($payment?->reference_number ?: '-').'.';
            }
        }

        if (Gate::forUser($user)->allows('view-accounting')) {
            $journal = JournalHeader::query()
                ->with('journalLines.chartOfAccount')
                ->where('branch_id', $sale->branch_id)
                ->where('reference_number', $sale->receipt_number)
                ->first();

            if ($journal === null) {
                $lines[] = 'Jurnal faktur ini tidak ditemukan.';
            } else {
                $lines[] = 'Jurnal '.$journal->reference_number.' tanggal '.$journal->transaction_date?->format('d/m/Y').':';
                foreach ($journal->journalLines as $line) {
                    $account = $line->chartOfAccount;
                    $label = $account ? "[{$account->code}] {$account->name}" : 'Akun';
                    $lines[] = '- '.$label.' debit '.$this->rupiah((float) $line->debit).', kredit '.$this->rupiah((float) $line->credit).'.';
                }
            }
        } else {
            $lines[] = 'Rincian jurnal hanya tampil bagi pengguna yang berhak melihat akuntansi.';
        }

        return [
            'found' => true,
            'summary' => implode("\n", $lines),
            'links' => [
                ['label' => 'Detail transaksi', 'url' => route('transactions.details', ['reference' => $sale->receipt_number])],
                ['label' => 'Terima Bayaran Piutang', 'url' => route('backoffice.payments.receivables.create')],
            ],
        ];
    }

    /**
     * @return array{found: bool, summary: string, links: array<int, array{label: string, url: string}>}
     */
    private function hidden(string $summary): array
    {
        return [
            'found' => false,
            'summary' => $summary,
            'links' => [],
        ];
    }

    private function rupiah(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
