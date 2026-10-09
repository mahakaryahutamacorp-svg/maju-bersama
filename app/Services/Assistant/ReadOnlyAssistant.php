<?php

namespace App\Services\Assistant;

use App\Models\AssistantInquiry;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

class ReadOnlyAssistant
{
    public function __construct(
        private readonly ReadOnlyToolkit $toolkit,
        private readonly AssistantModelClient $model,
    ) {}

    /**
     * @return array{answer: string, links: array<int, array{label: string, url: string}>, outcome: string}
     */
    public function ask(User $user, string $question): array
    {
        $question = trim($question);
        $limitKey = 'assistant:'.$user->id;
        $limit = max(1, (int) config('assistant.hourly_limit'));

        if (RateLimiter::tooManyAttempts($limitKey, $limit)) {
            return $this->finish($user, $question, 'rate_limited', [], 'Terlalu banyak pertanyaan. Coba lagi dalam beberapa menit.', []);
        }

        RateLimiter::hit($limitKey, 3600);

        if (AssistantContract::isActionRequest($question)) {
            $guide = $this->toolkit->run('search_guide', ['query' => $question], $user);
            $answer = "Saya tidak mencatat, mengubah, atau menghapus data. Langkahnya dikerjakan sendiri di halaman yang sesuai.\n\n".$guide['summary'];

            return $this->finish($user, $question, 'refused_action', ['search_guide'], $answer, $guide['links']);
        }

        if (! AssistantContract::mentionsApplication($question)) {
            return $this->finish(
                $user,
                $question,
                'out_of_scope',
                [],
                'Pertanyaan itu di luar ruang lingkup aplikasi. Saya hanya menjawab data dan cara pakai Maju Bersama.',
                [],
            );
        }

        $selection = $this->selectTools($question);
        if (config('assistant.remote')) {
            $selection = $this->model->chooseTools($question) ?? $selection;
            if ($selection === []) {
                $selection = $this->selectTools($question);
            }
        }

        $parts = [];
        $links = [];
        $used = [];
        $denied = [];
        $found = false;

        foreach ($selection as $call) {
            $name = (string) ($call['name'] ?? '');
            $arguments = is_array($call['arguments'] ?? null) ? $call['arguments'] : [];

            try {
                $result = $this->toolkit->run($name, $this->safeArguments($arguments), $user);
            } catch (AssistantToolDenied $deniedTool) {
                $denied[] = $deniedTool->tool;

                continue;
            }

            $used[] = $name;
            $found = $found || $result['found'];
            $parts[] = $result['summary'];
            foreach ($result['links'] as $link) {
                $links[$link['url']] = $link;
            }
        }

        if ($denied !== [] && $used === []) {
            return $this->finish(
                $user,
                $question,
                'refused_action',
                [],
                'Saya tidak melakukan tindakan itu. Asisten ini hanya membaca data dan menunjukkan halamannya.',
                [],
            );
        }

        if ($parts === []) {
            $parts[] = 'Tidak ditemukan di data yang boleh Anda lihat.';
            $found = false;
        }

        $outcome = $found ? 'answered' : 'not_found';

        return $this->finish($user, $question, $outcome, $used, implode("\n\n", $parts), array_values($links));
    }

    /**
     * @param  array<int, string>  $tools
     * @param  array<int, array{label: string, url: string}>  $links
     * @return array{answer: string, links: array<int, array{label: string, url: string}>, outcome: string}
     */
    private function finish(User $user, string $question, string $outcome, array $tools, string $answer, array $links): array
    {
        AssistantInquiry::query()->create([
            'user_id' => $user->id,
            'branch_id' => $user->branch_id,
            'question' => mb_substr($question, 0, 500),
            'outcome' => $outcome,
            'tools_used' => array_values($tools),
        ]);

        return [
            'answer' => $answer,
            'links' => $links,
            'outcome' => $outcome,
        ];
    }

    /**
     * @return array<int, array{name: string, arguments: array<string, mixed>}>
     */
    private function selectTools(string $question): array
    {
        $normalized = mb_strtolower($question);
        $term = $this->searchTerm($question);
        $isHowTo = preg_match('/cara|bagaimana|di mana|dimana|apa arti|menu|kode akun/u', $normalized) === 1;
        $wantsFigures = preg_match('/berapa|sisa|telusuri|inv-/iu', $question) === 1;

        if ($isHowTo && ! $wantsFigures) {
            return [['name' => 'search_guide', 'arguments' => ['query' => $question]]];
        }

        $calls = [];

        if (preg_match('/inv-[a-z0-9-]+/i', $question) === 1 || str_contains($normalized, 'faktur') || str_contains($normalized, 'telusuri') || str_contains($normalized, 'jurnal')) {
            $calls[] = ['name' => 'trace_document', 'arguments' => ['query' => $term]];
        }
        if (str_contains($normalized, 'piutang') || str_contains($normalized, 'debitur') || str_contains($normalized, 'tagihan')) {
            $calls[] = ['name' => 'list_receivables', 'arguments' => ['query' => $term]];
        }
        if (str_contains($normalized, 'stok') || str_contains($normalized, 'sku') || str_contains($normalized, 'gudang')) {
            $calls[] = ['name' => 'stock_on_hand', 'arguments' => ['query' => $term]];
        }
        if (str_contains($normalized, 'transaksi') || str_contains($normalized, 'terbaru') || str_contains($normalized, 'terakhir')) {
            $calls[] = ['name' => 'recent_transactions', 'arguments' => ['query' => $term]];
        }
        if (str_contains($normalized, 'penjualan') || str_contains($normalized, 'omzet')) {
            $calls[] = ['name' => 'sales_summary', 'arguments' => ['period' => str_contains($normalized, 'bulan') ? 'month' : 'today']];
        }
        if (str_contains($normalized, 'kas') || str_contains($normalized, 'bank') || str_contains($normalized, 'saldo')) {
            $calls[] = ['name' => 'cash_position', 'arguments' => []];
        }
        if ($calls === [] || preg_match('/cara|bagaimana|di mana|dimana|apa arti|menu|kode akun|1130|1110|2110/u', $normalized) === 1) {
            $calls[] = ['name' => 'search_guide', 'arguments' => ['query' => $question]];
        }

        return array_slice($calls, 0, 3);
    }

    private function searchTerm(string $question): string
    {
        if (preg_match('/inv-[a-z0-9-]+/i', $question, $match) === 1) {
            return $match[0];
        }

        $cleaned = preg_replace('/\b(berapa|sisa|stok|piutang|bagaimana|cara|tolong|dimana|di|mana|nama|pelanggan|debitur|hari|ini|bulan|posisi|kas|bank|penjualan|omzet|cek|lihat|tampilkan|saldo|faktur|telusuri|jurnal|barang|dan|yang|ada|apa|arti|transaksi|terbaru|terakhir|data|minta|diminta|semua|seluruh|cabang)\b/iu', ' ', $question) ?? $question;
        $cleaned = preg_replace('/[^\p{L}\p{N}\s-]/u', ' ', (string) $cleaned) ?? '';
        $cleaned = trim((string) preg_replace('/\s+/', ' ', $cleaned));

        return mb_substr($cleaned, 0, 60);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, string>
     */
    private function safeArguments(array $arguments): array
    {
        $safe = [];
        foreach (['query', 'period'] as $key) {
            if (isset($arguments[$key]) && is_scalar($arguments[$key])) {
                $safe[$key] = mb_substr(trim((string) $arguments[$key]), 0, 80);
            }
        }

        if (isset($safe['period']) && ! in_array($safe['period'], ['today', 'month'], true)) {
            $safe['period'] = 'today';
        }

        return $safe;
    }
}
