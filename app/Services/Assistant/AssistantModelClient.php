<?php

namespace App\Services\Assistant;

use Illuminate\Support\Facades\Http;
use Throwable;

class AssistantModelClient
{
    /**
     * @return array<int, array{name: string, arguments: array<string, mixed>}>|null
     */
    public function chooseTools(string $question): ?array
    {
        if (! config('assistant.remote')) {
            return null;
        }

        $apiKey = config('assistant.api_key');
        if (! is_string($apiKey) || $apiKey === '') {
            return null;
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(8)
                ->post(rtrim((string) config('assistant.base_url'), '/').'/chat/completions', [
                    'model' => config('assistant.model'),
                    'temperature' => 0,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => AssistantContract::ROLE.' Hanya pilih alat baca yang ada. Untuk cara pakai/penerapan, pilih usage_advice. Untuk nama orang/produk, pilih smart_search. Jangan menjawab di luar isi aplikasi. Jangan menjalankan simpan/ubah/hapus/tambah. Panggil paling banyak tiga alat.',
                        ],
                        ['role' => 'user', 'content' => $question],
                    ],
                    'tools' => $this->toolDefinitions(),
                    'tool_choice' => 'auto',
                ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $calls = $response->json('choices.0.message.tool_calls');
        if (! is_array($calls)) {
            return [];
        }

        $chosen = [];
        foreach (array_slice($calls, 0, 3) as $call) {
            $name = (string) ($call['function']['name'] ?? '');
            $rawArguments = $call['function']['arguments'] ?? '{}';
            $arguments = is_string($rawArguments) ? json_decode($rawArguments, true) : $rawArguments;
            $chosen[] = [
                'name' => $name,
                'arguments' => is_array($arguments) ? $arguments : [],
            ];
        }

        return $chosen;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function toolDefinitions(): array
    {
        $definitions = [
            ['search_guide', 'Cari letak menu, arti status, dan kode akun di dalam aplikasi.', ['query' => 'Kata kunci panduan']],
            ['usage_advice', 'Berikan saran langkah cara pakai/penerapan yang dikerjakan pengguna sendiri di UI.', ['query' => 'Topik cara pakai atau tujuan pengguna']],
            ['smart_search', 'Pencarian cerdas nama pelanggan, pemasok, produk, SKU, atau nomor dokumen; kembalikan ringkasan transaksi terkait.', ['query' => 'Nama, SKU, atau nomor dokumen']],
            ['stock_on_hand', 'Baca stok barang yang boleh dilihat pengguna.', ['query' => 'Nama atau SKU barang']],
            ['list_receivables', 'Baca piutang yang belum lunas.', ['query' => 'Nama debitur atau nomor faktur']],
            ['sales_summary', 'Baca ringkasan penjualan hari ini atau bulan ini.', ['period' => 'today atau month']],
            ['recent_transactions', 'Baca transaksi penjualan terbaru yang boleh dilihat pengguna.', ['query' => 'Nama pelanggan atau nomor faktur, kosong jika semua transaksi terbaru']],
            ['cash_position', 'Baca saldo kas dan bank.', []],
            ['trace_document', 'Telusuri satu faktur ke pembayaran dan jurnal.', ['query' => 'Nomor faktur atau nama debitur']],
        ];

        return array_map(function (array $definition) {
            [$name, $description, $properties] = $definition;
            $schemaProperties = [];
            foreach ($properties as $key => $hint) {
                $schemaProperties[$key] = ['type' => 'string', 'description' => $hint];
            }

            return [
                'type' => 'function',
                'function' => [
                    'name' => $name,
                    'description' => $description,
                    'parameters' => [
                        'type' => 'object',
                        'properties' => $schemaProperties,
                    ],
                ],
            ];
        }, $definitions);
    }
}
