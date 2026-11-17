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
     * @param  array<int, array{role?: string, text?: string}>  $history
     * @return array{answer: string, links: array<int, array{label: string, url: string}>, outcome: string}
     */
    public function ask(User $user, string $question, array $history = []): array
    {
        $question = trim($question);
        $history = $this->normalizeHistory($history);
        $resolved = $this->resolveWithHistory($question, $history);

        $limitKey = 'assistant:'.$user->id;
        $limit = max(1, (int) config('assistant.hourly_limit'));

        if (RateLimiter::tooManyAttempts($limitKey, $limit)) {
            return $this->finish($user, $question, 'rate_limited', [], 'Terlalu banyak pertanyaan. Coba lagi dalam beberapa menit.', []);
        }

        RateLimiter::hit($limitKey, 3600);

        if (AssistantContract::isActionRequest($resolved)) {
            $guide = $this->toolkit->run('search_guide', ['query' => $resolved], $user);
            $advice = $this->toolkit->run('usage_advice', ['query' => $resolved], $user);
            $answer = AssistantContract::ROLE."\n\nSaya tidak menulis, mengedit, menghapus, atau menambahkan data. Kerjakan sendiri di halaman yang sesuai.\n\n"
                .$guide['summary']."\n\n".$advice['summary'];
            $links = [];
            foreach (array_merge($guide['links'], $advice['links']) as $link) {
                $links[$link['url']] = $link;
            }

            return $this->finish($user, $question, 'refused_action', ['search_guide', 'usage_advice'], $answer, array_values($links));
        }

        if (! AssistantContract::mentionsApplication($resolved)) {
            return $this->finish(
                $user,
                $question,
                'out_of_scope',
                [],
                'Pertanyaan itu di luar ruang lingkup aplikasi. Saya hanya menjawab data dan cara pakai Maju Bersama.',
                [],
            );
        }

        $selection = $this->selectTools($resolved);
        if (config('assistant.remote')) {
            $selection = $this->model->chooseTools($resolved) ?? $selection;
            if ($selection === []) {
                $selection = $this->selectTools($resolved);
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
            if ($result['found'] || in_array($name, ['search_guide', 'usage_advice'], true)) {
                $parts[] = $result['summary'];
                foreach ($result['links'] as $link) {
                    $links[$link['url']] = $link;
                }
            } elseif ($parts === [] && $name === 'smart_search') {
                $parts[] = $result['summary'];
                foreach ($result['links'] as $link) {
                    $links[$link['url']] = $link;
                }
            }
        }

        if ($denied !== [] && $used === []) {
            return $this->finish(
                $user,
                $question,
                'refused_action',
                [],
                AssistantContract::ROLE,
                [],
            );
        }

        if ($parts === []) {
            $parts[] = 'Tidak ditemukan di data yang boleh Anda lihat.';
            $found = false;
        }

        $answer = implode("\n\n", $parts);
        if ($history !== [] && $resolved !== $question) {
            $answer = "Memakai konteks percakapan sebelumnya.\n\n".$answer;
        }

        $outcome = $found ? 'answered' : 'not_found';

        return $this->finish($user, $question, $outcome, $used, $answer, array_values($links));
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
     * @param  array<int, array{role?: string, text?: string}>  $history
     * @return array<int, array{role: string, text: string}>
     */
    private function normalizeHistory(array $history): array
    {
        $normalized = [];

        foreach (array_slice($history, -3) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $role = (string) ($item['role'] ?? '');
            $text = trim((string) ($item['text'] ?? ''));
            if (! in_array($role, ['user', 'assistant'], true) || $text === '') {
                continue;
            }

            $normalized[] = [
                'role' => $role,
                'text' => mb_substr($text, 0, 500),
            ];
        }

        return $normalized;
    }

    /**
     * Gabungkan follow-up dengan 2–3 pertanyaan user sebelumnya (hanya di memori request).
     *
     * @param  array<int, array{role: string, text: string}>  $history
     */
    private function resolveWithHistory(string $question, array $history): string
    {
        if ($history === []) {
            return $question;
        }

        $priorUserText = collect($history)
            ->where('role', 'user')
            ->pluck('text')
            ->implode(' ');

        if ($priorUserText === '') {
            return $question;
        }

        if (AssistantContract::isFollowUp($question)) {
            return trim($priorUserText.' '.$question);
        }

        // Saran tanpa nama, tapi riwayat punya entitas: "bagaimana caranya bayar?" setelah "hutang budi".
        if (AssistantContract::wantsAdvice($question) && AssistantContract::entityTerm($question) === '') {
            $priorEntity = AssistantContract::entityTerm($priorUserText);
            if ($priorEntity !== '') {
                return trim($question.' '.$priorEntity);
            }
        }

        return $question;
    }

    /**
     * @return array<int, array{name: string, arguments: array<string, mixed>}>
     */
    private function selectTools(string $question): array
    {
        $normalized = mb_strtolower($question);
        $term = $this->searchTerm($question);
        $wantsAdvice = AssistantContract::wantsAdvice($question);
        $isLocation = preg_match('/di\s*mana|dimana|letak\s+menu|menu\s+mana|halaman\s+(mana|apa)|apa\s+arti|kode\s+akun/u', $normalized) === 1;
        $wantsFigures = preg_match('/berapa|sisa|telusuri|inv-|gr-|po-/iu', $question) === 1;
        $hasEntity = $term !== '' && mb_strlen($term) >= 2 && ! preg_match('/^(menu|halaman|data|info|saran)$/iu', $term);

        if (($wantsAdvice || $isLocation) && ! $wantsFigures) {
            $calls = [];
            if ($hasEntity) {
                $calls[] = ['name' => 'smart_search', 'arguments' => ['query' => $term]];
            }
            if ($isLocation || ! $wantsAdvice) {
                $calls[] = ['name' => 'search_guide', 'arguments' => ['query' => $question]];
            }
            if ($wantsAdvice) {
                $calls[] = ['name' => 'usage_advice', 'arguments' => ['query' => $question]];
            }

            return array_slice($calls !== [] ? $calls : [
                ['name' => 'usage_advice', 'arguments' => ['query' => $question]],
            ], 0, 3);
        }

        if (preg_match('/inv-[a-z0-9-]+/i', $question) === 1 || str_contains($normalized, 'telusuri')) {
            $calls = [['name' => 'trace_document', 'arguments' => ['query' => $term !== '' ? $term : $question]]];
            if ($wantsAdvice) {
                $calls[] = ['name' => 'usage_advice', 'arguments' => ['query' => $question]];
            }

            return array_slice($calls, 0, 3);
        }

        if ($hasEntity && ! preg_match('/\b(hari ini|bulan ini|posisi kas|omzet)\b/u', $normalized)) {
            $calls = [['name' => 'smart_search', 'arguments' => ['query' => $term]]];
            if ($wantsAdvice) {
                $calls[] = ['name' => 'usage_advice', 'arguments' => ['query' => $question]];
            }

            return array_slice($calls, 0, 3);
        }

        $calls = [];

        if (str_contains($normalized, 'faktur') || str_contains($normalized, 'jurnal')) {
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
        if ($wantsAdvice) {
            $calls[] = ['name' => 'usage_advice', 'arguments' => ['query' => $question]];
        }
        if ($calls === [] || preg_match('/menu|kode akun|1130|1110|2110/u', $normalized) === 1) {
            $calls[] = ['name' => 'search_guide', 'arguments' => ['query' => $question]];
        }

        return array_slice($calls, 0, 3);
    }

    private function searchTerm(string $question): string
    {
        return AssistantContract::entityTerm($question);
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
