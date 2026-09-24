<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk #{{ $sale->receipt_number }} | Maju Bersama POS</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12px;
            line-height: 1.3;
            color: #000;
            background: #f1f5f9;
            padding: 20px 10px;
        }
        .receipt-container {
            width: 100%;
            max-width: 58mm;
            margin: 0 auto;
            background: #fff;
            padding: 12px 10px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .text-left { text-align: left; }
        .font-bold { font-weight: bold; }
        .uppercase { text-transform: uppercase; }
        .divider {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }
        .divider-double {
            border-top: 2px double #000;
            margin: 8px 0;
        }
        .shop-title {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 2px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
            font-size: 11px;
        }
        .item-row {
            margin-bottom: 6px;
        }
        .item-name {
            font-weight: bold;
            word-break: break-word;
        }
        .item-calc {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }
        .total-row {
            font-size: 13px;
            font-weight: bold;
            margin: 4px 0;
        }
        .footer-text {
            font-size: 10px;
            margin-top: 8px;
            text-align: center;
        }
        .no-print-bar {
            max-width: 58mm;
            margin: 0 auto 15px auto;
            display: flex;
            gap: 8px;
        }
        .btn {
            flex: 1;
            padding: 8px 12px;
            font-family: sans-serif;
            font-size: 12px;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
            border: none;
            text-align: center;
            text-decoration: none;
        }
        .btn-print { background: #0284c7; color: #fff; }
        .btn-back { background: #64748b; color: #fff; }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .no-print-bar {
                display: none !important;
            }
            .receipt-container {
                box-shadow: none;
                padding: 0;
                max-width: 100%;
                width: 100%;
            }
            @page {
                size: 58mm auto;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Tombol Aksi di Layar Komputer -->
    <div class="no-print-bar">
        <button class="btn btn-print" onclick="window.print()">🖨️ Cetak Ulang</button>
        <a href="/pos" class="btn btn-back">← Kasir</a>
    </div>

    <!-- Konten Struk Kertas Thermal -->
    <div class="receipt-container">
        <!-- Header Toko & Cabang -->
        <div class="text-center">
            <h1 class="shop-title uppercase">MAJU BERSAMA</h1>
            <p class="font-bold">{{ $sale->branch?->name ?? 'CABANG PUSAT' }}</p>
            <p style="font-size: 10px;">{{ $sale->branch?->address ?? 'Jl. Operasional Toko' }}</p>
            @if ($sale->branch?->phone)
                <p style="font-size: 10px;">Telp: {{ $sale->branch->phone }}</p>
            @endif
        </div>

        <div class="divider"></div>

        <!-- Meta Informasi Transaksi -->
        <div class="info-row">
            <span>No: {{ $sale->receipt_number }}</span>
            <span>{{ $sale->created_at?->format('d/m/y H:i') }}</span>
        </div>
        <div class="info-row">
            <span>Kasir: {{ $sale->creator?->name ?? 'Kasir' }}</span>
            <span class="uppercase">Bayar: {{ $sale->payment_method ?? 'TUNAI' }}</span>
        </div>

        <div class="divider"></div>

        <!-- Daftar Barang -->
        <div>
            @php
                $calculatedTotal = 0;
            @endphp
            @foreach ($sale->items as $item)
                @php
                    $unitPrice = (int) ($item->price / 100);
                    $itemSubtotal = (int) ($item->subtotal / 100);
                    $calculatedTotal += $itemSubtotal;
                @endphp
                <div class="item-row">
                    <div class="item-name">{{ $item->product?->name ?? 'Item Produk' }}</div>
                    <div class="item-calc">
                        <span>{{ $item->quantity }} x {{ number_format($unitPrice, 0, ',', '.') }}</span>
                        <span class="font-bold">{{ number_format($itemSubtotal, 0, ',', '.') }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="divider-double"></div>

        <!-- Ringkasan Pembayaran -->
        @php
            $realTotal = (int) ($sale->total_amount / 100);
            $cash = $cashTendered ? (int) $cashTendered : $realTotal;
            $change = $changeDue !== null ? (int) $changeDue : max(0, $cash - $realTotal);
            $hasDiscount = !empty($sale->discount_amount) && (float) $sale->discount_amount > 0;
        @endphp

        @if ($hasDiscount)
            <div class="summary-row">
                <span>SUBTOTAL:</span>
                <span>Rp {{ number_format($calculatedTotal, 0, ',', '.') }}</span>
            </div>
            <div class="summary-row">
                <span>DISKON:</span>
                <span>- Rp {{ number_format((int) $sale->discount_amount, 0, ',', '.') }}</span>
            </div>
        @endif

        <div class="summary-row total-row">
            <span>TOTAL:</span>
            <span>Rp {{ number_format($realTotal, 0, ',', '.') }}</span>
        </div>
        <div class="summary-row">
            <span>TUNAI:</span>
            <span>Rp {{ number_format($cash, 0, ',', '.') }}</span>
        </div>
        <div class="summary-row">
            <span>KEMBALIAN:</span>
            <span>Rp {{ number_format($change, 0, ',', '.') }}</span>
        </div>

        <div class="divider"></div>

        <!-- Footer Struk -->
        <div class="footer-text">
            <p class="font-bold">TERIMA KASIH</p>
            <p>Atas Kunjungan &amp; Belanja Anda</p>
            <p style="margin-top: 4px; font-size: 9px; color: #555;">
                Barang yang sudah dibeli tidak dapat ditukar/dikembalikan.
            </p>
        </div>
    </div>

    <!-- Auto Print Script -->
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            // Beri jeda 300ms agar rendering browser selesai sebelum memicu dialog print
            setTimeout(() => {
                window.print();
            }, 300);
        });
    </script>
</body>
</html>
