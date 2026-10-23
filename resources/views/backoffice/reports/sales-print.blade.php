<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap Penjualan</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111; margin: 24px; }
        h1 { font-size: 18px; margin: 0; }
        p { margin: 4px 0; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 12px; }
        th, td { border: 1px solid #222; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .toolbar { margin-bottom: 16px; }
        @media print {
            .toolbar { display: none; }
            body { margin: 12mm; }
            @page { size: A4; margin: 12mm; }
        }
    </style>
</head>
<body>
    @php
        $scope = $lines['scope'];
        $title = match ($scope) {
            'taxable' => 'Khusus Pajak (Ada PPN)',
            'exempt' => 'Khusus Non-Pajak (Bebas PPN)',
            default => 'Semua / Gabungan',
        };
        $money = fn (string $amount) => 'Rp '.number_format((float) $amount, 2, ',', '.');
    @endphp

    <div class="toolbar">
        <button type="button" onclick="window.print()">Cetak</button>
    </div>

    <h1>MAJU BERSAMA — Rekap Penjualan</h1>
    <p>{{ $branchName }}</p>
    <p>Periode {{ \Carbon\Carbon::parse($startDate)->isoFormat('D MMMM Y') }} s/d {{ \Carbon\Carbon::parse($endDate)->isoFormat('D MMMM Y') }}</p>
    <p>Filter: {{ $title }}</p>

    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Nomor Nota</th>
                <th>Item</th>
                @if ($scope === 'taxable')
                    <th class="num">DPP</th>
                    <th class="num">PPN</th>
                    <th class="num">Total</th>
                @elseif ($scope === 'exempt')
                    <th class="num">Total</th>
                @else
                    <th class="num">Non Pajak</th>
                    <th class="num">DPP</th>
                    <th class="num">PPN</th>
                    <th class="num">Total</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse ($lines['rows'] as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['receipt'] }}</td>
                    <td>{{ $row['item'] }}</td>
                    @if ($scope === 'taxable')
                        <td class="num">{{ $money($row['dpp']) }}</td>
                        <td class="num">{{ $money($row['tax']) }}</td>
                        <td class="num">{{ $money($row['total']) }}</td>
                    @elseif ($scope === 'exempt')
                        <td class="num">{{ $money($row['total']) }}</td>
                    @else
                        <td class="num">{{ $money($row['non_tax']) }}</td>
                        <td class="num">{{ $money($row['dpp']) }}</td>
                        <td class="num">{{ $money($row['tax']) }}</td>
                        <td class="num">{{ $money($row['total']) }}</td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="6">Tidak ada baris pada filter ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if ($scope === 'all')
        <p>Subtotal non-pajak: {{ $money($lines['non_tax_total']) }}</p>
        <p>DPP: {{ $money($lines['dpp_total']) }}</p>
        <p>PPN: {{ $money($lines['ppn_total']) }}</p>
        <p><strong>Total akhir: {{ $money($lines['grand_total']) }}</strong></p>
    @else
        <p><strong>Total: {{ $money($lines['grand_total']) }}</strong></p>
    @endif
</body>
</html>
